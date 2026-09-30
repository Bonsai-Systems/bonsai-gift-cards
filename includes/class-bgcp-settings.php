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
	const OPTION_REPORT_EMAILS = 'bgcp_report_emails';
	const OPTION_REPORT_FREQUENCIES = 'bgcp_report_frequencies';
	const OPTION_HARD_COPY_FEE = 'bgcp_hard_copy_fee';

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
		BGCP_Admin_UI::enqueue();
		wp_enqueue_style( 'bgcp-admin', BGCP_PLUGIN_URL . 'assets/css/admin.css', array( BGCP_Admin_UI::HANDLE ), BGCP_VERSION );
		wp_enqueue_script( 'bgcp-admin', BGCP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BGCP_VERSION, true );
		wp_localize_script(
			'bgcp-admin',
			'bgcpAdmin',
			array(
				'mediaTitle'  => __( 'Select gift card image', 'bgcp' ),
				'mediaButton' => __( 'Use this image', 'bgcp' ),
				'previewAlt'  => __( 'Gift card image preview', 'bgcp' ),
			)
		);
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

	/**
	 * Fee charged for a printed/posted physical gift card, per card.
	 * Defaults to £10 — editable so it can be changed, or reused on
	 * another client site, without a code edit.
	 */
	public static function get_hard_copy_fee() {
		$fee = get_option( self::OPTION_HARD_COPY_FEE, '' );
		return '' === $fee ? 10.0 : (float) $fee;
	}

	/**
	 * Stored as one address per line; returns only the ones that pass
	 * is_email() so a typo in one address doesn't block the rest.
	 */
	public static function get_report_emails() {
		$raw    = get_option( self::OPTION_REPORT_EMAILS, '' );
		$lines  = preg_split( '/[\r\n,]+/', $raw );
		$emails = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line && is_email( $line ) ) {
				$emails[] = $line;
			}
		}

		return array_unique( $emails );
	}

	public static function get_report_emails_raw() {
		return get_option( self::OPTION_REPORT_EMAILS, '' );
	}

	public static function get_report_frequencies() {
		$frequencies = get_option( self::OPTION_REPORT_FREQUENCIES, array() );
		if ( ! is_array( $frequencies ) ) {
			$frequencies = array();
		}
		return array_values( array_intersect( $frequencies, array( 'weekly', 'monthly' ) ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}

		$image_id = self::get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="wrap bonsai-ui bonsai-ui--narrow">
			<?php
			BGCP_Admin_UI::header(
				__( 'Gift Card Settings', 'bgcp' ),
				__( 'The gift card email, default expiry for manually-created cards, printed card fee and sales reports.', 'bgcp' ),
				array(
					array(
						'label' => __( 'Gift card codes', 'bgcp' ),
						'url'   => admin_url( 'edit.php?post_type=product&page=bgcp-cards' ),
					),
				)
			);
			?>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bgcp' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bgcp_save_settings' ); ?>
				<input type="hidden" name="action" value="bgcp_save_settings" />

				<section class="bonsai-ui-card" aria-labelledby="bgcp-email-title">
					<h2 class="bonsai-ui-card__title" id="bgcp-email-title"><?php esc_html_e( 'Gift card email', 'bgcp' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Gift card image', 'bgcp' ); ?></th>
							<td>
								<input type="hidden" name="bgcp_gift_card_image_id" id="bgcp_gift_card_image_id" value="<?php echo esc_attr( $image_id ); ?>" />
								<div id="bgcp-image-preview" class="bgcp-image-preview">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Gift card image preview', 'bgcp' ); ?>" />
									<?php endif; ?>
								</div>
								<p class="bonsai-ui-actions">
									<button type="button" class="button" id="bgcp-upload-image"><?php esc_html_e( 'Select image', 'bgcp' ); ?></button>
									<button type="button" class="button" id="bgcp-remove-image"<?php echo $image_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'bgcp' ); ?></button>
								</p>
								<p class="description"><?php esc_html_e( 'Shown in the gift card email.', 'bgcp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bgcp_email_intro"><?php esc_html_e( 'Email intro text', 'bgcp' ); ?></label></th>
							<td>
								<textarea name="bgcp_email_intro" id="bgcp_email_intro" rows="3" class="large-text"><?php echo esc_textarea( get_option( self::OPTION_EMAIL_INTRO, '' ) ); ?></textarea>
							</td>
						</tr>
					</table>
				</section>

				<section class="bonsai-ui-card" aria-labelledby="bgcp-cards-title">
					<h2 class="bonsai-ui-card__title" id="bgcp-cards-title"><?php esc_html_e( 'Cards and pricing', 'bgcp' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="bgcp_default_expiry_months"><?php esc_html_e( 'Default expiry for manually-created cards (months)', 'bgcp' ); ?></label></th>
							<td>
								<input type="number" min="0" step="1" name="bgcp_default_expiry_months" id="bgcp_default_expiry_months" value="<?php echo esc_attr( self::get_expiry_months() ); ?>" />
								<p class="description"><?php esc_html_e( '0 = never expires. Only applies to cards created manually from the admin list — cards sold via a product use that product\'s own expiry setting.', 'bgcp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bgcp_hard_copy_fee"><?php esc_html_e( 'Printed card fee (£)', 'bgcp' ); ?></label></th>
							<td>
								<input type="number" min="0" step="0.01" name="bgcp_hard_copy_fee" id="bgcp_hard_copy_fee" value="<?php echo esc_attr( self::get_hard_copy_fee() ); ?>" />
								<p class="description"><?php esc_html_e( 'Charged per gift card when the customer chooses to have a printed card posted to the recipient, on top of the card\'s own value.', 'bgcp' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="bonsai-ui-card" aria-labelledby="bgcp-reports-title">
					<h2 class="bonsai-ui-card__title" id="bgcp-reports-title"><?php esc_html_e( 'Sales reports', 'bgcp' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="bgcp_report_emails"><?php esc_html_e( 'Sales report email addresses', 'bgcp' ); ?></label></th>
							<td>
								<textarea name="bgcp_report_emails" id="bgcp_report_emails" rows="3" class="large-text" placeholder="one@example.com&#10;two@example.com"><?php echo esc_textarea( self::get_report_emails_raw() ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One address per line (or comma separated). Leave blank to disable. The report is sent to every valid address listed.', 'bgcp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sales report frequency', 'bgcp' ); ?></th>
							<td>
								<?php $frequencies = self::get_report_frequencies(); ?>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Sales report frequency', 'bgcp' ); ?></legend>
									<label>
										<input type="checkbox" name="bgcp_report_frequencies[]" value="weekly" <?php checked( in_array( 'weekly', $frequencies, true ) ); ?> />
										<?php esc_html_e( 'Weekly', 'bgcp' ); ?>
									</label>
									<label>
										<input type="checkbox" name="bgcp_report_frequencies[]" value="monthly" <?php checked( in_array( 'monthly', $frequencies, true ) ); ?> />
										<?php esc_html_e( 'Monthly', 'bgcp' ); ?>
									</label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Tick both to receive a weekly and a monthly report. A CSV of every gift card created in that period is emailed to the address(es) above.', 'bgcp' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

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
		update_option( self::OPTION_HARD_COPY_FEE, isset( $_POST['bgcp_hard_copy_fee'] ) ? wc_format_decimal( wp_unslash( $_POST['bgcp_hard_copy_fee'] ) ) : 10.0 );

		$report_emails = isset( $_POST['bgcp_report_emails'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bgcp_report_emails'] ) ) : '';
		update_option( self::OPTION_REPORT_EMAILS, $report_emails );

		$report_frequencies = isset( $_POST['bgcp_report_frequencies'] ) ? (array) wp_unslash( $_POST['bgcp_report_frequencies'] ) : array();
		$report_frequencies = array_values( array_intersect( array_map( 'sanitize_text_field', $report_frequencies ), array( 'weekly', 'monthly' ) ) );
		update_option( self::OPTION_REPORT_FREQUENCIES, $report_frequencies );

		if ( class_exists( 'BGCP_Report' ) ) {
			BGCP_Report::reschedule();
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'edit.php?post_type=product&page=bgcp-settings' ) ) );
		exit;
	}
}
