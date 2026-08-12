<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prefixes the Stripe payment description with "voucher-sale-stripe-{order
 * number}" for orders that include a gift card product — makes gift card
 * sales identifiable in the Stripe dashboard/customer statement, separate
 * from regular product orders. No-ops harmlessly if WooCommerce Stripe
 * Gateway isn't the active gateway, since the filter it hooks is simply
 * never fired in that case.
 */
class BGCP_Stripe {

	public static function init() {
		add_filter( 'wc_stripe_generate_payment_request', array( __CLASS__, 'maybe_prefix_description' ), 10, 2 );
	}

	public static function maybe_prefix_description( $request, $order ) {
		if ( ! $order instanceof WC_Order || ! self::order_has_gift_card( $order ) ) {
			return $request;
		}

		$request['description'] = 'voucher-sale-stripe-' . $order->get_order_number();

		return $request;
	}

	private static function order_has_gift_card( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( '' !== $item->get_meta( '_bgcp_amount', true ) ) {
				return true;
			}
		}
		return false;
	}
}
