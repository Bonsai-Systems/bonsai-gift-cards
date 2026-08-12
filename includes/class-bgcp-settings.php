<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gift Cards → Settings — the client's gift card image, the email intro
 * copy, and the default expiry applied to manually-created cards.
 */
class BGCP_Settings {

	const OPTION_IMAGE_ID   = 'bgcp_gift_card_image_id';
	const OPTION_EMAIL_INTRO = 'bgcp_email_intro';
	const OPTION_EXPIRY_MONTHS = 'bgcp_default_expiry_months';
	const OPTION_REPORT_EMAIL = 'bgcp_report_email';
	const OPTION_REPORT_FREQUENCY = 'bgcp_report_frequency';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_bgcp_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Gift Card Settings', 'bgcp' ),
			__( 'Gift Card Settings', 'bgcp' ),
			'manage_woocommerce',
			'bgcp-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'product_page_bgcp-settings' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'bgcp-admin', BGCP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BGCP_VERSION, true );
	}

	public static function get_image_id() {
		return absint( get_option( self::OPTION_IMAGE_ID, 0 ) );
	}

	public static function get_image_url( $size = 'large' ) {
		$image_id = self::get_image_id();
		if ( ! $image_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, $size );
		return $url ? $url : '';
	}

	public static function get_email_intro() {
		$intro = get_option( self::OPTION_EMAIL_INTRO, '' );
		if ( '' === $intro ) {
			$intro = __( "You've been sent a gift card.", 'bgcp' );
		}
		return $intro;
	}

	public static function get_expiry_months() {
		return absint( get_option( self::OPTION_EXPIRY_MONTHS, 0 ) );
	}

	public static function get_report_email() {
		return get_option( self::OPTION_REPORT_EMAIL, '' );
	}

	public static function get_report_frequency() {
		$frequency = get_option( self::OPTION_REPORT_FREQUENCY, '' );
		return in_array( $frequency, array( 'weekly', 'monthly' ), true ) ? $frequency : '';
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}

		$image_id = self::get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gift Card Settings', 'bgcp' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bgcp' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bgcp_save_settings' ); ?>
				<input type="hidden" name="action" value="bgcp_save_settings" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Gift card image', 'bgcp' ); ?></th>
						<td>
							<input type="hidden" name="bgcp_gift_card_image_id" id="bgcp_gift_card_image_id" value="<?php echo esc_attr( $image_id ); ?>" />
							<div id="bgcp-image-preview">
								<?php if ( $image_url ) : ?>
									<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:400px; display:block;" />
								<?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" id="bgcp-upload-image"><?php esc_html_e( 'Select image', 'bgcp' ); ?></button>
								<button type="button" class="button" id="bgcp-remove-image" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'bgcp' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'Shown in the gift card email.', 'bgcp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bgcp_email_intro"><?php esc_html_e( 'Email intro text', 'bgcp' ); ?></label></th>
						<td>
							<textarea name="bgcp_email_intro" id="bgcp_email_intro" rows="3" class="large-text"><?php echo esc_textarea( get_option( self::OPTION_EMAIL_INTRO, '' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="bgcp_default_expiry_months"><?php esc_html_e( 'Default expiry for manually-created cards (months)', 'bgcp' ); ?></label></th>
						<td>
							<input type="number" min="0" step="1" name="bgcp_default_expiry_months" id="bgcp_default_expiry_months" value="<?php echo esc_attr( self::get_expiry_months() ); ?>" />
							<p class="description"><?php esc_html_e( '0 = never expires. Only applies to cards created manually from the admin list — cards sold via a product use that product\'s own expiry setting.', 'bgcp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bgcp_report_email"><?php esc_html_e( 'Sales report email address', 'bgcp' ); ?></label></th>
						<td>
							<input type="email" name="bgcp_report_email" id="bgcp_report_email" class="regular-text" value="<?php echo esc_attr( self::get_report_email() ); ?>" />
							<p class="description"><?php esc_html_e( 'Where to send the gift card sales CSV report. Leave blank to disable.', 'bgcp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bgcp_report_frequency"><?php esc_html_e( 'Sales report frequency', 'bgcp' ); ?></label></th>
						<td>
							<select name="bgcp_report_frequency" id="bgcp_report_frequency">
								<option value="" <?php selected( self::get_report_frequency(), '' ); ?>><?php esc_html_e( 'Off', 'bgcp' ); ?></option>
								<option value="weekly" <?php selected( self::get_report_frequency(), 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'bgcp' ); ?></option>
								<option value="monthly" <?php selected( self::get_report_frequency(), 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'bgcp' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'A CSV of every gift card created in that period is emailed to the address above. Requires a report email address to be set.', 'bgcp' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'bgcp' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}
		check_admin_referer( 'bgcp_save_settings' );

		update_option( self::OPTION_IMAGE_ID, isset( $_POST['bgcp_gift_card_image_id'] ) ? absint( $_POST['bgcp_gift_card_image_id'] ) : 0 );
		update_option( self::OPTION_EMAIL_INTRO, isset( $_POST['bgcp_email_intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bgcp_email_intro'] ) ) : '' );
		update_option( self::OPTION_EXPIRY_MONTHS, isset( $_POST['bgcp_default_expiry_months'] ) ? absint( $_POST['bgcp_default_expiry_months'] ) : 0 );

		$report_email = isset( $_POST['bgcp_report_email'] ) ? sanitize_email( wp_unslash( $_POST['bgcp_report_email'] ) ) : '';
		update_option( self::OPTION_REPORT_EMAIL, $report_email );

		$report_frequency = isset( $_POST['bgcp_report_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcp_report_frequency'] ) ) : '';
		$report_frequency = in_array( $report_frequency, array( 'weekly', 'monthly' ), true ) ? $report_frequency : '';
		update_option( self::OPTION_REPORT_FREQUENCY, $report_frequency );

		if ( class_exists( 'BGCP_Report' ) ) {
			BGCP_Report::reschedule();
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'edit.php?post_type=product&page=bgcp-settings' ) ) );
		exit;
	}
}
