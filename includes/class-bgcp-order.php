<?php
defined( 'ABSPATH' ) || exit;

/**
 * Watches for paid orders containing gift card line items, mints the
 * codes, and triggers the branded email. Hooked to payment_complete
 * (not order status alone) so it fires reliably for Stripe.
 */
class BGCP_Order {

	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_issue_gift_cards' ) );
		// Belt and braces for gateways/flows that mark paid without ever calling payment_complete().
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_issue_gift_cards' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_issue_gift_cards' ) );

		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'maybe_void_gift_cards' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'maybe_void_gift_cards' ) );
	}

	public static function maybe_issue_gift_cards( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Don't double-issue if we've already minted codes for this order.
		if ( 'yes' === $order->get_meta( '_bgcp_issued' ) ) {
			return;
		}

		$issued_any = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			$amount = $item->get_meta( '_bgcp_amount' );
			if ( ! $amount ) {
				continue;
			}

			$qty = max( 1, (int) $item->get_quantity() );

			for ( $i = 0; $i < $qty; $i++ ) {
				$expiry = null;
				$product = $item->get_product();
				if ( $product ) {
					$months = get_post_meta( $product->get_id(), '_bgcp_expiry_months', true );
					if ( $months ) {
						$expiry = gmdate( 'Y-m-d H:i:s', strtotime( '+' . (int) $months . ' months' ) );
					}
				}

				$code = BGCP_DB::create_card(
					array(
						'initial_amount'  => $amount,
						'order_id'        => $order->get_id(),
						'recipient_name'  => $item->get_meta( '_bgcp_recipient_name' ),
						'recipient_email' => $item->get_meta( '_bgcp_recipient_email' ) ?: $order->get_billing_email(), // phpcs:ignore
						'purchaser_email' => $order->get_billing_email(),
						'message'         => $item->get_meta( '_bgcp_message' ),
						'expires_at'      => $expiry,
					)
				);

				if ( is_wp_error( $code ) ) {
					error_log( 'BGCP: failed to issue gift card for order ' . $order->get_id() . ' — ' . $code->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
					continue;
				}

				$card_id = BGCP_DB::get_card_by_code( $code )->id;

				$delivery_date = $item->get_meta( '_bgcp_delivery_date' );
				$send_now      = empty( $delivery_date ) || strtotime( $delivery_date ) <= time();

				if ( $send_now ) {
					self::send_gift_card_email( $card_id, $order );
				} else {
					// Scheduled delivery — single-fire cron event.
					wp_schedule_single_event(
						strtotime( $delivery_date . ' 09:00:00' ),
						'bgcp_send_scheduled_gift_card',
						array( $card_id, $order->get_id() )
					);
				}

				$issued_any = true;
			}
		}

		if ( $issued_any ) {
			$order->update_meta_data( '_bgcp_issued', 'yes' );
			$order->save();
		}
	}

	public static function send_gift_card_email( $card_id, $order ) {
		$card = BGCP_DB::get_card( $card_id );
		if ( ! $card ) {
			return;
		}

		$mailer = WC()->mailer();
		$email  = $mailer->emails['BGCP_Email_Gift_Card'] ?? null;
		if ( $email ) {
			$email->trigger( $card, $order );
		}
	}

	/**
	 * Voids any un-redeemed gift cards if the order is cancelled/refunded
	 * shortly after purchase (e.g. failed Stripe payment retry, chargeback).
	 * Cards that have already had funds spent are left alone — that's a
	 * manual-review situation, not something to silently zero out.
	 */
	public static function maybe_void_gift_cards( $order_id ) {
		global $wpdb;
		$table = BGCP_DB::table_name();

		$cards = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ) ); // phpcs:ignore

		foreach ( $cards as $card ) {
			if ( (float) $card->balance === (float) $card->initial_amount ) {
				BGCP_DB::set_status( $card->code, 'void' );
			}
		}
	}
}

add_action(
	'bgcp_send_scheduled_gift_card',
	function ( $card_id, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			BGCP_Order::send_gift_card_email( $card_id, $order );
		}
	},
	10,
	2
);
