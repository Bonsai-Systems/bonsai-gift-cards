<?php
defined( 'ABSPATH' ) || exit;

/**
 * Turns any Simple Product into a gift card by ticking a checkbox in
 * Product Data > General. Handles the frontend amount / recipient fields
 * and stashes them on the cart item so BGCP_Order can pick them up later.
 */
class BGCP_Product {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'admin_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_admin_fields' ) );

		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'frontend_fields' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_hard_copy_fields' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_add_order_item_meta', array( __CLASS__, 'legacy_noop' ) ); // kept for older themes calling this hook name
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'set_cart_item_price' ), 10, 1 );
		// Cart items are rebuilt from the WC session on every later page load
		// (cart, checkout, a fresh visit) — without this, the price above only
		// survives the single request it was added on and silently reverts to
		// the product's base price everywhere else.
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'set_cart_item_price' ), 10, 1 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_hard_copy_fee' ) );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'friendly_meta_key' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect_to_checkout' ) );
	}

	/**
	 * Gift cards go straight to checkout instead of the cart page — there's
	 * nothing useful to do on the cart for a single voucher, and it matches
	 * how gift card purchases work on most sites. Only applies to gift card
	 * products; every other WooCommerce product keeps its normal behaviour.
	 */
	public static function redirect_to_checkout( $url ) {
		$product_id = isset( $_REQUEST['add-to-cart'] ) ? absint( $_REQUEST['add-to-cart'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $product_id && self::is_gift_card( $product_id ) ) {
			return wc_get_checkout_url();
		}

		return $url;
	}

	public static function legacy_noop() {}

	public static function admin_fields() {
		global $post;

		echo '<div class="options_group bgcp_options">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_bgcp_is_gift_card',
				'label'       => __( 'Gift card?', 'bgcp' ),
				'description' => __( 'Sell this product as a gift card.', 'bgcp' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_bgcp_preset_amounts',
				'label'       => __( 'Preset amounts (£)', 'bgcp' ),
				'description' => __( 'Comma separated, e.g. 25,50,75,100. Leave blank to only allow a custom amount.', 'bgcp' ),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_bgcp_min_amount',
				'label'             => __( 'Minimum custom amount (£)', 'bgcp' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_bgcp_max_amount',
				'label'             => __( 'Maximum custom amount (£)', 'bgcp' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_bgcp_expiry_months',
				'label'       => __( 'Expires after (months)', 'bgcp' ),
				'type'        => 'number',
				'description' => __( 'Leave blank for no expiry.', 'bgcp' ),
				'desc_tip'    => true,
			)
		);

		echo '</div>';
	}

	public static function save_admin_fields( $post_id ) {
		$is_gift_card = isset( $_POST['_bgcp_is_gift_card'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
		update_post_meta( $post_id, '_bgcp_is_gift_card', $is_gift_card );

		$fields = array( '_bgcp_preset_amounts', '_bgcp_min_amount', '_bgcp_max_amount', '_bgcp_expiry_months' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}

	public static function is_gift_card( $product_id ) {
		return 'yes' === get_post_meta( $product_id, '_bgcp_is_gift_card', true );
	}

	public static function frontend_fields() {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product || ! self::is_gift_card( $product->get_id() ) ) {
			return;
		}

		$presets = get_post_meta( $product->get_id(), '_bgcp_preset_amounts', true );
		$presets = $presets ? array_map( 'trim', explode( ',', $presets ) ) : array();
		$min     = get_post_meta( $product->get_id(), '_bgcp_min_amount', true );
		$max     = get_post_meta( $product->get_id(), '_bgcp_max_amount', true );

		wc_get_template(
			'gift-card-purchase-fields.php',
			array(
				'presets'       => $presets,
				'min'           => $min ?: 5,   // phpcs:ignore
				'max'           => $max ?: 500, // phpcs:ignore
				'hard_copy_fee' => BGCP_Settings::get_hard_copy_fee(),
			),
			'',
			BGCP_PLUGIN_DIR . 'templates/'
		);
	}

	public static function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_the_ID() );
		if ( ! $product || ! self::is_gift_card( $product->get_id() ) ) {
			return;
		}

		wp_enqueue_script(
			'bgcp-product-fields',
			BGCP_PLUGIN_URL . 'assets/js/product-fields.js',
			array( 'jquery' ),
			BGCP_VERSION,
			true
		);
	}

	/**
	 * Requires a postal address when "send a printed card" is ticked —
	 * there's a real printing/postage cost tied to it, so unlike the
	 * (optional) email recipient fields this one has to be enforced
	 * server-side, not just in the browser.
	 */
	public static function validate_hard_copy_fields( $passed, $product_id, $quantity ) {
		if ( ! self::is_gift_card( $product_id ) ) {
			return $passed;
		}

		if ( empty( $_POST['bgcp_hard_copy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $passed;
		}

		$required = array(
			'bgcp_ship_name'      => __( 'Recipient name', 'bgcp' ),
			'bgcp_ship_address_1' => __( 'Address line 1', 'bgcp' ),
			'bgcp_ship_city'      => __( 'Town / City', 'bgcp' ),
			'bgcp_ship_postcode'  => __( 'Postcode', 'bgcp' ),
			'bgcp_ship_country'   => __( 'Country', 'bgcp' ),
		);

		foreach ( $required as $field => $label ) {
			if ( empty( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				/* translators: %s: missing field label, e.g. Postcode */
				wc_add_notice( sprintf( __( '%s is required to post a printed gift card.', 'bgcp' ), $label ), 'error' );
				$passed = false;
			}
		}

		return $passed;
	}

	public static function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( ! self::is_gift_card( $product_id ) ) {
			return $cart_item_data;
		}

		if ( ! empty( $_POST['bgcp_amount'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$cart_item_data['bgcp_gift_card'] = array(
				'amount'          => wc_format_decimal( wp_unslash( $_POST['bgcp_amount'] ) ), // phpcs:ignore
				'recipient_name'  => isset( $_POST['bgcp_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_recipient_name'] ) ) : '', // phpcs:ignore
				'recipient_email' => isset( $_POST['bgcp_recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['bgcp_recipient_email'] ) ) : '', // phpcs:ignore
				'message'         => isset( $_POST['bgcp_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bgcp_message'] ) ) : '', // phpcs:ignore
				'delivery_date'   => isset( $_POST['bgcp_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_delivery_date'] ) ) : '', // phpcs:ignore
				'hard_copy'       => ! empty( $_POST['bgcp_hard_copy'] ) ? 'yes' : '', // phpcs:ignore
			);

			if ( ! empty( $_POST['bgcp_hard_copy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$cart_item_data['bgcp_gift_card']['ship_name']      = isset( $_POST['bgcp_ship_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_ship_name'] ) ) : ''; // phpcs:ignore
				$cart_item_data['bgcp_gift_card']['ship_address_1'] = isset( $_POST['bgcp_ship_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_ship_address_1'] ) ) : ''; // phpcs:ignore
				$cart_item_data['bgcp_gift_card']['ship_address_2'] = isset( $_POST['bgcp_ship_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_ship_address_2'] ) ) : ''; // phpcs:ignore
				$cart_item_data['bgcp_gift_card']['ship_city']      = isset( $_POST['bgcp_ship_city'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_ship_city'] ) ) : ''; // phpcs:ignore
				$cart_item_data['bgcp_gift_card']['ship_postcode']  = isset( $_POST['bgcp_ship_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_ship_postcode'] ) ) : ''; // phpcs:ignore
				$cart_item_data['bgcp_gift_card']['ship_country']   = isset( $_POST['bgcp_ship_country'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_ship_country'] ) ) : ''; // phpcs:ignore
			}

			// Unique key so identical amounts don't merge into one cart line with qty 2.
			$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
		}

		return $cart_item_data;
	}

	public static function set_cart_item_price( $cart_item ) {
		if ( ! empty( $cart_item['bgcp_gift_card']['amount'] ) ) {
			$cart_item['data']->set_price( (float) $cart_item['bgcp_gift_card']['amount'] );
		}
		return $cart_item;
	}

	public static function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['bgcp_gift_card'] ) ) {
			return $item_data;
		}
		$gc = $cart_item['bgcp_gift_card'];

		if ( ! empty( $gc['recipient_name'] ) ) {
			$item_data[] = array(
				'name'  => __( 'Recipient', 'bgcp' ),
				'value' => esc_html( $gc['recipient_name'] ),
			);
		}
		if ( ! empty( $gc['delivery_date'] ) ) {
			$item_data[] = array(
				'name'  => __( 'Delivery date', 'bgcp' ),
				'value' => esc_html( $gc['delivery_date'] ),
			);
		}

		if ( ! empty( $gc['hard_copy'] ) ) {
			$item_data[] = array(
				'name'  => __( 'Printed card by post', 'bgcp' ),
				'value' => esc_html( self::format_ship_address( $gc ) ),
			);
		}

		return $item_data;
	}

	public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['bgcp_gift_card'] ) ) {
			return;
		}
		$gc = $values['bgcp_gift_card'];

		$item->add_meta_data( '_bgcp_amount', $gc['amount'] );
		$item->add_meta_data( '_bgcp_recipient_name', $gc['recipient_name'] );
		$item->add_meta_data( '_bgcp_recipient_email', $gc['recipient_email'] );
		$item->add_meta_data( '_bgcp_message', $gc['message'] );
		$item->add_meta_data( '_bgcp_delivery_date', $gc['delivery_date'] );

		if ( ! empty( $gc['hard_copy'] ) ) {
			$item->add_meta_data( '_bgcp_hard_copy', 'yes' );
			$item->add_meta_data( '_bgcp_ship_name', $gc['ship_name'] );
			$item->add_meta_data( '_bgcp_ship_address_1', $gc['ship_address_1'] );
			$item->add_meta_data( '_bgcp_ship_address_2', $gc['ship_address_2'] );
			$item->add_meta_data( '_bgcp_ship_city', $gc['ship_city'] );
			$item->add_meta_data( '_bgcp_ship_postcode', $gc['ship_postcode'] );
			$item->add_meta_data( '_bgcp_ship_country', $gc['ship_country'] );
		}
	}

	/**
	 * Adds one combined fee line covering every cart item that has a
	 * printed card requested, so a customer ordering several gift cards
	 * with only some printed isn't overcharged.
	 */
	public static function apply_hard_copy_fee( $cart ) {
		$fee_amount = BGCP_Settings::get_hard_copy_fee();
		if ( $fee_amount <= 0 ) {
			return;
		}

		$count = 0;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['bgcp_gift_card']['hard_copy'] ) ) {
				$count += max( 1, (int) $cart_item['quantity'] );
			}
		}

		if ( $count > 0 ) {
			$cart->add_fee( __( 'Printed & posted gift card(s)', 'bgcp' ), $fee_amount * $count, false );
		}
	}

	private static function format_ship_address( array $gc ) {
		$parts = array_filter(
			array(
				$gc['ship_name'] ?? '',
				$gc['ship_address_1'] ?? '',
				$gc['ship_address_2'] ?? '',
				$gc['ship_city'] ?? '',
				$gc['ship_postcode'] ?? '',
				$gc['ship_country'] ?? '',
			)
		);
		return implode( ', ', $parts );
	}

	public static function friendly_meta_key( $display_key, $meta ) {
		$labels = array(
			'_bgcp_hard_copy'      => __( 'Printed card by post', 'bgcp' ),
			'_bgcp_ship_name'      => __( 'Postal recipient name', 'bgcp' ),
			'_bgcp_ship_address_1' => __( 'Postal address line 1', 'bgcp' ),
			'_bgcp_ship_address_2' => __( 'Postal address line 2', 'bgcp' ),
			'_bgcp_ship_city'      => __( 'Postal town/city', 'bgcp' ),
			'_bgcp_ship_postcode'  => __( 'Postal postcode', 'bgcp' ),
			'_bgcp_ship_country'   => __( 'Postal country', 'bgcp' ),
		);

		return $labels[ $meta->key ] ?? $display_key;
	}
}
