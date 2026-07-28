<?php
/**
 * Plugin Name: Bonsai Gift Card Plugin
 * Plugin URI:  https://bonsaidigitalcollective.co.uk
 * Description: Simple WooCommerce gift card system — sell, email, redeem and check balance on gift cards. Built for WooCommerce Blocks Cart/Checkout with a client-configurable gift card image.
 * Version:     1.0.0
 * Author:      The Bonsai Digital Collective
 * Author URI:  https://bonsaidigitalcollective.co.uk
 * Text Domain: bgcp
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BGCP_VERSION', '1.0.0' );
define( 'BGCP_PLUGIN_FILE', __FILE__ );
define( 'BGCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BGCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function bgcp_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Bonsai Gift Card Plugin requires WooCommerce to be installed and active.', 'bgcp' ) .
		'</p></div>';
}

add_action( 'plugins_loaded', 'bgcp_init' );

function bgcp_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bgcp_missing_woocommerce_notice' );
		return;
	}

	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-db.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-settings.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-product.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-order.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-email.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-cart.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-shortcode.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-admin.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-rest.php';

	BGCP_Settings::init();
	BGCP_Product::init();
	BGCP_Order::init();
	BGCP_Cart::instance();
	BGCP_Shortcode::init();
	BGCP_Admin::instance();
	BGCP_REST::init();

	add_filter( 'woocommerce_email_classes', 'bgcp_register_email_class' );
}

function bgcp_register_email_class( $email_classes ) {
	$email_classes['BGCP_Email_Gift_Card'] = new BGCP_Email_Gift_Card();
	return $email_classes;
}

register_activation_hook( __FILE__, array( 'BGCP_DB', 'create_table' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * The "Have a gift card?" field in the Blocks Cart/Checkout sidebar
 * requires registering against WooCommerce Blocks' own integration
 * registry — it's a separate hook from the general plugin bootstrap
 * above because Blocks may load after 'plugins_loaded'.
 */
add_action(
	'woocommerce_blocks_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) || ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			return;
		}

		require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-blocks-integration.php';

		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new BGCP_Blocks_Integration() );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new BGCP_Blocks_Integration() );
			}
		);
	}
);
