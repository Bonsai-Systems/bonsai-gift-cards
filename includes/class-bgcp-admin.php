<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen to view gift cards, manually generate cards (e.g. for
 * someone who rings up wanting a voucher), and adjust balances/notes.
 * Sits alongside the Settings page under Products → Gift Cards.
 */
class BGCP_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_bgcp_manual_create', array( $this, 'handle_manual_create' ) );
		add_action( 'admin_post_bgcp_adjust_status', array( $this, 'handle_adjust_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'product_page_bgcp-cards' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'bgcp-admin', BGCP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BGCP_VERSION, true );
		wp_localize_script(
			'bgcp-admin',
			'bgcpAdmin',
			array(
				'confirmDisable' => __( 'Disable this gift card?', 'bgcp' ),
			)
		);
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Gift Cards', 'bgcp' ),
			__( 'Gift Card Codes', 'bgcp' ),
			'manage_woocommerce',
			'bgcp-cards',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$cards  = BGCP_DB::get_cards( array( 'search' => $search, 'limit' => 100 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gift Card Codes', 'bgcp' ); ?></h1>

			<h2><?php esc_html_e( 'Create a card manually', 'bgcp' ); ?></h2>
			<p class="description"><?php esc_html_e( 'For phone orders or in-person sales that skip checkout.', 'bgcp' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:2em;">
				<?php wp_nonce_field( 'bgcp_manual_create' ); ?>
				<input type="hidden" name="action" value="bgcp_manual_create" />
				<table class="form-table">
					<tr>
						<th><label for="bgcp_amount"><?php esc_html_e( 'Amount (£)', 'bgcp' ); ?></label></th>
						<td><input type="number" step="0.01" min="1" max="1000" name="amount" id="bgcp_amount" required /></td>
					</tr>
					<tr>
						<th><label for="bgcp_recipient_email"><?php esc_html_e( 'Recipient email', 'bgcp' ); ?></label></th>
						<td><input type="email" name="recipient_email" id="bgcp_recipient_email" required /></td>
					</tr>
					<tr>
						<th><label for="bgcp_recipient_name"><?php esc_html_e( 'Recipient name', 'bgcp' ); ?></label></th>
						<td><input type="text" name="recipient_name" id="bgcp_recipient_name" /></td>
					</tr>
					<tr>
						<th><label for="bgcp_send_email"><?php esc_html_e( 'Send email now?', 'bgcp' ); ?></label></th>
						<td><input type="checkbox" name="send_email" id="bgcp_send_email" value="yes" checked /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Create Gift Card', 'bgcp' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Existing cards', 'bgcp' ); ?></h2>
			<form method="get" style="margin-bottom:1em;">
				<input type="hidden" name="post_type" value="product" />
				<input type="hidden" name="page" value="bgcp-cards" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search code or email…', 'bgcp' ); ?>" />
				<?php submit_button( __( 'Search', 'bgcp' ), '', '', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Code', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Initial', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Balance', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Order', 'bgcp' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bgcp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $cards ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No gift cards found.', 'bgcp' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $cards as $card ) : ?>
							<tr>
								<td><code><?php echo esc_html( $card->code ); ?></code></td>
								<td><?php echo esc_html( $card->recipient_email ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $card->initial_amount ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $card->balance ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $card->status ) ); ?></td>
								<td><?php echo $card->expires_at ? esc_html( date_i18n( 'j M Y', strtotime( $card->expires_at ) ) ) : esc_html__( 'Never', 'bgcp' ); ?></td>
								<td><?php echo $card->order_id ? esc_html( '#' . $card->order_id ) : '—'; ?></td>
								<td>
									<?php if ( 'active' === $card->status ) : ?>
										<a href="<?php echo esc_url( $this->status_action_url( $card->code, 'disabled' ) ); ?>" class="bgcp-confirm-disable"><?php esc_html_e( 'Disable', 'bgcp' ); ?></a>
									<?php else : ?>
										<a href="<?php echo esc_url( $this->status_action_url( $card->code, 'active' ) ); ?>"><?php esc_html_e( 'Re-enable', 'bgcp' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function status_action_url( $code, $status ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'bgcp_adjust_status',
					'code'   => $code,
					'status' => $status,
				),
				admin_url( 'admin-post.php' )
			),
			'bgcp_adjust_status_' . $code
		);
	}

	public function handle_manual_create() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}
		check_admin_referer( 'bgcp_manual_create' );

		$amount          = isset( $_POST['amount'] ) ? wc_format_decimal( wp_unslash( $_POST['amount'] ) ) : 0;
		$recipient_email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '';
		$recipient_name  = isset( $_POST['recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) ) : '';
		$send_email      = isset( $_POST['send_email'] );

		if ( $amount > 0 && $amount <= 1000 && is_email( $recipient_email ) ) {
			$expiry_months = BGCP_Settings::get_expiry_months();
			$expires_at    = $expiry_months > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_months} months" ) ) : null;

			$code = BGCP_DB::create_card(
				array(
					'initial_amount'  => $amount,
					'recipient_email' => $recipient_email,
					'recipient_name'  => $recipient_name,
					'expires_at'      => $expires_at,
				)
			);

			if ( ! is_wp_error( $code ) && $send_email ) {
				$card = BGCP_DB::get_card_by_code( $code );
				do_action( 'bgcp_send_gift_card_email', $card );
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=bgcp-cards' ) );
		exit;
	}

	public function handle_adjust_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}

		$code   = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		check_admin_referer( 'bgcp_adjust_status_' . $code );

		if ( $code && in_array( $status, array( 'active', 'disabled' ), true ) ) {
			BGCP_DB::set_status( $code, $status );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=bgcp-cards' ) );
		exit;
	}
}
