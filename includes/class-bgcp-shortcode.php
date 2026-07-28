<?php
defined( 'ABSPATH' ) || exit;

/**
 * [bgcp_balance_check] — drop this on the Gift Vouchers page so customers
 * can look up what's left on their card without ringing up.
 */
class BGCP_Shortcode {

	public static function init() {
		add_shortcode( 'bgcp_balance_check', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'bgcp_balance_check' ) ) {
			return;
		}

		wp_enqueue_script(
			'bgcp-balance-check',
			BGCP_PLUGIN_URL . 'assets/js/balance-check.js',
			array( 'jquery' ),
			BGCP_VERSION,
			true
		);
		wp_localize_script(
			'bgcp-balance-check',
			'bgcpBalance',
			array(
				'restUrl' => esc_url_raw( rest_url( 'bgcp/v1/balance' ) ),
				'i18n'    => array(
					'checking' => __( 'Checking…', 'bgcp' ),
					'error'    => __( 'Something went wrong. Please try again.', 'bgcp' ),
				),
			)
		);
		wp_enqueue_style( 'bgcp-checkout-block', BGCP_PLUGIN_URL . 'assets/css/gift-card.css', array(), BGCP_VERSION );
	}

	public static function render() {
		ob_start();
		?>
		<div class="bgcp-balance-check">
			<form id="bgcp-balance-form">
				<label for="bgcp-balance-code"><?php esc_html_e( 'Gift card code', 'bgcp' ); ?></label>
				<div class="bgcp-field-row">
					<input type="text" id="bgcp-balance-code" placeholder="<?php esc_attr_e( 'e.g. GC-4F82-9X3K', 'bgcp' ); ?>" required />
					<button type="submit"><?php esc_html_e( 'Check balance', 'bgcp' ); ?></button>
				</div>
			</form>
			<p id="bgcp-balance-result"></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
