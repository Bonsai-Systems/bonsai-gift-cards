<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Registers the redeem-gift-card field into the Cart & Checkout blocks.
 *
 * The Blocks UI has no vanilla-JS extension point for the built-in slots —
 * @woocommerce/blocks-checkout's ExperimentalOrderMeta Slot is React-only —
 * so this script uses wp.element.createElement directly (no JSX, no build
 * step) rather than pulling in a full React toolchain for one small field.
 */
class BGCP_Blocks_Integration implements IntegrationInterface {

	public function get_name() {
		return 'bgcp-gift-cards';
	}

	public function initialize() {
		$handle = 'bgcp-checkout-block';

		wp_register_script(
			$handle,
			BGCP_PLUGIN_URL . 'assets/js/checkout-block.js',
			array(
				'wc-blocks-checkout',
				'wp-element',
				'wp-plugins',
				'wp-i18n',
				'wp-data',
			),
			BGCP_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'bgcpCheckout',
			array(
				'restUrl' => esc_url_raw( rest_url( 'bgcp/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'label'       => __( 'Have a gift card?', 'bgcp' ),
					'placeholder' => __( 'Enter code', 'bgcp' ),
					'apply'       => __( 'Apply', 'bgcp' ),
					'remove'      => __( 'Remove', 'bgcp' ),
					'applying'    => __( 'Applying…', 'bgcp' ),
				),
			)
		);

		wp_enqueue_style(
			'bgcp-checkout-block',
			BGCP_PLUGIN_URL . 'assets/css/gift-card.css',
			array(),
			BGCP_VERSION
		);
	}

	public function get_script_handles() {
		return array( 'bgcp-checkout-block' );
	}

	public function get_editor_script_handles() {
		return array();
	}

	public function get_script_data() {
		return array();
	}
}
