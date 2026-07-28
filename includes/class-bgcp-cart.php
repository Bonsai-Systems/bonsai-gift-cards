<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applying/removing gift cards at Cart & Checkout.
 *
 * The redeem field itself lives in the Blocks checkout sidebar (see
 * BGCP_Blocks_Integration + assets/js/checkout-block.js), driven via the
 * REST routes in BGCP_REST. This class is the shared engine underneath
 * both: session storage of applied codes, the cart-fee calculation, and
 * the atomic balance deduction — BGCP_REST just calls the static methods
 * below rather than duplicating any of this.
 *
 * The actual balance deduction happens once, atomically, at order-creation
 * time (not when the fee is first applied) to avoid double-spending a code
 * across two abandoned carts, and to guarantee nothing is deducted unless
 * an order actually gets created.
 */
class BGCP_Cart {

	const SESSION_KEY = 'bgcp_applied_codes';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_fee' ) );

		// Throwing an Exception inside this hook is WooCommerce's documented
		// way to abort order creation — so if a balance check fails here,
		// no order is created and nothing has been deducted yet.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'deduct_and_attach_order_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_session_after_order' ) );
	}

	/**
	 * Codes currently applied to the cart, as an array of
	 * [ 'code' => ..., 'amount' => ... ] (amount = usable amount at time of applying).
	 */
	private static function get_applied() {
		if ( ! WC()->session ) {
			return array();
		}
		$applied = WC()->session->get( self::SESSION_KEY );
		return is_array( $applied ) ? $applied : array();
	}

	private static function set_applied( array $applied ) {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY, array_values( $applied ) );
		}
	}

	private static function clear_applied() {
		if ( WC()->session ) {
			WC()->session->__unset( self::SESSION_KEY );
		}
	}

	public static function get_applied_codes() {
		return wp_list_pluck( self::get_applied(), 'code' );
	}

	/**
	 * Validate a code against the live cart total and add it to the
	 * session. Returns the refreshed list of applied cards, or WP_Error.
	 */
	public static function apply_code( $code ) {
		$code = sanitize_text_field( wp_unslash( (string) $code ) );
		$card = BGCP_DB::get_card_by_code( $code );

		if ( ! $card ) {
			return new WP_Error( 'bgcp_not_found', __( "We couldn't find a gift card with that code.", 'bgcp' ) );
		}
		if ( 'active' !== $card->status ) {
			return new WP_Error( 'bgcp_inactive', __( 'This gift card is no longer active.', 'bgcp' ) );
		}
		if ( $card->expires_at && strtotime( $card->expires_at ) < time() ) {
			return new WP_Error( 'bgcp_expired', __( 'This gift card has expired.', 'bgcp' ) );
		}
		if ( (float) $card->balance <= 0 ) {
			return new WP_Error( 'bgcp_empty', __( 'This gift card has no remaining balance.', 'bgcp' ) );
		}

		$applied = self::get_applied();
		foreach ( $applied as $entry ) {
			if ( $entry['code'] === $card->code ) {
				return new WP_Error( 'bgcp_already_applied', __( 'That gift card is already applied.', 'bgcp' ) );
			}
		}

		$remaining_total = self::remaining_cart_total( $applied );
		$usable_amount    = min( (float) $card->balance, $remaining_total );

		if ( $usable_amount <= 0 ) {
			return new WP_Error( 'bgcp_no_balance_needed', __( 'The order total is already covered.', 'bgcp' ) );
		}

		$applied[] = array(
			'code'   => $card->code,
			'amount' => $usable_amount,
		);
		self::set_applied( $applied );

		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}

		return self::get_applied_cards();
	}

	public static function remove_code( $code ) {
		$code    = sanitize_text_field( wp_unslash( (string) $code ) );
		$applied = array_values(
			array_filter(
				self::get_applied(),
				function ( $entry ) use ( $code ) {
					return $entry['code'] !== $code;
				}
			)
		);
		self::set_applied( $applied );

		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}

		return self::get_applied_cards();
	}

	/**
	 * Applied codes re-checked against their live DB balance, for display.
	 */
	public static function get_applied_cards() {
		$cards = array();
		foreach ( self::get_applied() as $entry ) {
			$card = BGCP_DB::get_card_by_code( $entry['code'] );
			if ( $card ) {
				$cards[] = array(
					'code'    => $card->code,
					'balance' => (float) $card->balance,
				);
			}
		}
		return $cards;
	}

	private static function remaining_cart_total( array $already_applied ) {
		if ( ! WC()->cart ) {
			return 0.0;
		}
		$cart_total = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		$reserved   = array_sum( wp_list_pluck( $already_applied, 'amount' ) );
		return max( 0.0, $cart_total - $reserved );
	}

	/**
	 * Adds one combined "Gift Card" fee line covering every applied code,
	 * re-validated against live balances (a code may have been spent
	 * elsewhere, expired, or disabled since it was applied).
	 */
	public function apply_fee( $cart ) {
		$applied = self::get_applied();
		if ( empty( $applied ) ) {
			return;
		}

		$total_usable = 0.0;
		$codes        = array();
		$cart_total   = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
		$remaining    = $cart_total;

		foreach ( $applied as $entry ) {
			$card = BGCP_DB::get_card_by_code( $entry['code'] );
			if ( ! $card || 'active' !== $card->status || (float) $card->balance <= 0 ) {
				continue;
			}
			$usable_amount = min( (float) $card->balance, $remaining, (float) $entry['amount'] );
			if ( $usable_amount <= 0 ) {
				continue;
			}
			$total_usable += $usable_amount;
			$remaining    -= $usable_amount;
			$codes[]       = $card->code;
		}

		if ( $total_usable > 0 ) {
			$cart->add_fee( __( 'Gift Card', 'bgcp' ) . ' (' . implode( ', ', $codes ) . ')', -$total_usable, false );
		}
	}

	/**
	 * Fires while the order object is being built (all earlier checkout
	 * validation has already passed). Re-validates every applied code
	 * fresh against the DB and deducts each atomically. Throwing here
	 * aborts order creation cleanly if a balance changed underneath us.
	 */
	public function deduct_and_attach_order_meta( $order, $data ) {
		$applied = self::get_applied();
		if ( empty( $applied ) ) {
			return;
		}

		$cart_total = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		$remaining  = $cart_total;
		$deducted   = array();

		foreach ( $applied as $entry ) {
			$usable_amount = min( (float) $entry['amount'], $remaining );
			if ( $usable_amount <= 0 ) {
				continue;
			}

			$result = BGCP_DB::adjust_balance( $entry['code'], -$usable_amount, 'Redeemed at checkout' );

			if ( is_wp_error( $result ) ) {
				// Roll back any codes already deducted for this order before aborting.
				foreach ( $deducted as $rollback ) {
					BGCP_DB::adjust_balance( $rollback['code'], $rollback['amount'], 'Rollback — checkout aborted' );
				}
				self::clear_applied();
				throw new Exception(
					sprintf(
						/* translators: 1: gift card code, 2: error message */
						__( 'Gift card %1$s could not be applied: %2$s. Please remove it and try again.', 'bgcp' ),
						esc_html( $entry['code'] ),
						$result->get_error_message()
					)
				);
			}

			$deducted[] = array(
				'code'   => $entry['code'],
				'amount' => $usable_amount,
			);
			$remaining -= $usable_amount;
		}

		if ( ! empty( $deducted ) ) {
			$order->update_meta_data( '_bgcp_redeemed_codes', wp_json_encode( $deducted ) );
			$order->update_meta_data( '_bgcp_redeemed_amount', array_sum( wp_list_pluck( $deducted, 'amount' ) ) );
		}
	}

	public function clear_session_after_order( $order_id ) {
		self::clear_applied();
	}
}
