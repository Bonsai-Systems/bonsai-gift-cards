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
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_add_order_item_meta', array( __CLASS__, 'legacy_noop' ) ); // kept for older themes calling this hook name
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'set_cart_item_price' ), 10, 1 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );
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
		global $product;

		if ( ! self::is_gift_card( $product->get_id() ) ) {
			return;
		}

		$presets = get_post_meta( $product->get_id(), '_bgcp_preset_amounts', true );
		$presets = $presets ? array_map( 'trim', explode( ',', $presets ) ) : array();
		$min     = get_post_meta( $product->get_id(), '_bgcp_min_amount', true );
		$max     = get_post_meta( $product->get_id(), '_bgcp_max_amount', true );

		wc_get_template(
			'gift-card-purchase-fields.php',
			array(
				'presets' => $presets,
				'min'     => $min ?: 5,   // phpcs:ignore
				'max'     => $max ?: 500, // phpcs:ignore
			),
			'',
			BGCP_PLUGIN_DIR . 'templates/'
		);
	}

	public static function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}
		global $product;
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
			);
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
	}
}
