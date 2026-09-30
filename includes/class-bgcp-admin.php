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
		add_action( 'admin_post_bgcp_edit_balance', array( $this, 'handle_edit_balance' ) );
		add_action( 'admin_post_bgcp_redeem_card', array( $this, 'handle_redeem_card' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'product_page_bgcp-cards' !== $hook ) {
			return;
		}
		BGCP_Admin_UI::enqueue();
		wp_enqueue_style( 'bgcp-admin', BGCP_PLUGIN_URL . 'assets/css/admin.css', array( BGCP_Admin_UI::HANDLE ), BGCP_VERSION );
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$cards  = BGCP_DB::get_cards( array( 'search' => $search, 'limit' => 100 ) );
		$view   = isset( $_GET['bgcp_view'] ) && 'create' === $_GET['bgcp_view'] ? 'create' : 'existing';
		?>
		<div class="wrap bonsai-ui">
			<?php
			BGCP_Admin_UI::header(
				__( 'Gift Card Codes', 'bgcp' ),
				__( 'Look up, redeem, correct and disable gift cards, or create one for a phone or in-person sale.', 'bgcp' ),
				array(
					array(
						'label' => __( 'Settings', 'bgcp' ),
						'url'   => admin_url( 'edit.php?post_type=product&page=bgcp-settings' ),
					),
				)
			);
			?>

			<?php if ( isset( $_GET['bgcp_notice'] ) && 'balance_updated' === $_GET['bgcp_notice'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Balance updated.', 'bgcp' ); ?></p></div>
			<?php elseif ( isset( $_GET['bgcp_notice'] ) && 'redeemed' === $_GET['bgcp_notice'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gift card redeemed.', 'bgcp' ); ?></p></div>
			<?php elseif ( isset( $_GET['bgcp_notice'] ) && 'created' === $_GET['bgcp_notice'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gift card created.', 'bgcp' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['bgcp_error'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['bgcp_error'] ) ) ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( remove_query_arg( 'bgcp_view' ) ); ?>" class="nav-tab <?php echo 'existing' === $view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Existing cards', 'bgcp' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'bgcp_view', 'create' ) ); ?>" class="nav-tab <?php echo 'create' === $view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Create a card manually', 'bgcp' ); ?></a>
			</h2>

			<?php if ( 'create' === $view ) : ?>

				<div class="notice notice-info inline bgcp-guide">
					<p>
						<strong><?php esc_html_e( 'When to use this:', 'bgcp' ); ?></strong>
						<?php esc_html_e( "Use this form when someone wants a gift card without going through the website checkout — a phone order, or a customer paying in person with cash or card.", 'bgcp' ); ?>
					</p>
					<p>
						<?php esc_html_e( "1. Enter the amount to load onto the card and who it's for.", 'bgcp' ); ?><br />
						<?php esc_html_e( '2. Tick "Send email now" to email the card straight to them, or untick it if you\'d rather hand over the code yourself (e.g. printed or written down).', 'bgcp' ); ?><br />
						<?php esc_html_e( '3. Click "Create Gift Card". The new code will appear on the Existing Cards tab.', 'bgcp' ); ?>
					</p>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bgcp_manual_create' ); ?>
					<input type="hidden" name="action" value="bgcp_manual_create" />
					<section class="bonsai-ui-card" aria-labelledby="bgcp-create-title">
						<h2 class="bonsai-ui-card__title" id="bgcp-create-title"><?php esc_html_e( 'New gift card', 'bgcp' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="bgcp_amount"><?php esc_html_e( 'Amount (£)', 'bgcp' ); ?></label></th>
								<td><input type="number" step="0.01" min="1" max="1000" name="amount" id="bgcp_amount" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="bgcp_recipient_email"><?php esc_html_e( 'Recipient email', 'bgcp' ); ?></label></th>
								<td><input type="email" name="recipient_email" id="bgcp_recipient_email" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="bgcp_recipient_name"><?php esc_html_e( 'Recipient name', 'bgcp' ); ?></label></th>
								<td><input type="text" name="recipient_name" id="bgcp_recipient_name" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="bgcp_send_email"><?php esc_html_e( 'Send email now?', 'bgcp' ); ?></label></th>
								<td><input type="checkbox" name="send_email" id="bgcp_send_email" value="yes" checked /></td>
							</tr>
						</table>
					</section>
					<?php submit_button( __( 'Create Gift Card', 'bgcp' ) ); ?>
				</form>

			<?php else : ?>

				<div class="notice notice-info inline bgcp-guide">
					<p><strong><?php esc_html_e( 'Quick guide:', 'bgcp' ); ?></strong></p>
					<p>
						<?php esc_html_e( 'Search by code or customer email using the box below.', 'bgcp' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Redeem', 'bgcp' ); ?></strong> — <?php esc_html_e( 'a customer is spending some or all of the card in person or over the phone (not through the website checkout). Click Redeem, enter how much they\'re spending, and it comes off the balance.', 'bgcp' ); ?><br />
						<strong><?php esc_html_e( 'Edit', 'bgcp' ); ?></strong> — <?php esc_html_e( "the balance was entered wrong and needs correcting. Click Edit and type in the correct balance — this overwrites it directly, it doesn't add or subtract.", 'bgcp' ); ?><br />
						<strong><?php esc_html_e( 'Disable / Re-enable', 'bgcp' ); ?></strong> — <?php esc_html_e( 'Disable stops a card being used at all, e.g. if it\'s reported lost or fraudulent. Re-enable brings it back into use.', 'bgcp' ); ?>
					</p>
				</div>

				<section class="bonsai-ui-card" aria-labelledby="bgcp-list-title">
					<div class="bonsai-ui-card__head">
						<h2 class="bonsai-ui-card__title" id="bgcp-list-title"><?php esc_html_e( 'Existing cards', 'bgcp' ); ?></h2>
						<form method="get" class="bonsai-ui-actions" role="search">
							<input type="hidden" name="post_type" value="product" />
							<input type="hidden" name="page" value="bgcp-cards" />
							<label for="bgcp-search" class="screen-reader-text"><?php esc_html_e( 'Search gift cards', 'bgcp' ); ?></label>
							<input type="search" id="bgcp-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search code or email…', 'bgcp' ); ?>" />
							<?php submit_button( __( 'Search', 'bgcp' ), '', '', false ); ?>
						</form>
					</div>

				<div class="bgcp-table-wrap">
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
							<th><?php esc_html_e( 'Paid via', 'bgcp' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bgcp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $cards ) ) : ?>
							<tr><td colspan="9"><?php esc_html_e( 'No gift cards found.', 'bgcp' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $cards as $card ) : ?>
								<tr>
									<td><code><?php echo esc_html( $card->code ); ?></code></td>
									<td><?php echo esc_html( $card->recipient_email ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $card->initial_amount ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $card->balance ) ); ?></td>
									<td><span class="bonsai-ui-badge <?php echo esc_attr( 'active' === $card->status ? 'bonsai-ui-badge--success' : 'bonsai-ui-badge--error' ); ?>"><?php echo esc_html( ucfirst( $card->status ) ); ?></span></td>
									<td><?php echo $card->expires_at ? esc_html( date_i18n( 'j M Y', strtotime( $card->expires_at ) ) ) : esc_html__( 'Never', 'bgcp' ); ?></td>
									<td><?php echo $card->order_id ? esc_html( '#' . $card->order_id ) : '—'; ?></td>
									<td><?php echo esc_html( self::payment_method_label( $card ) ); ?></td>
									<td>
										<?php if ( 'active' === $card->status && $card->balance > 0 ) : ?>
											<a href="#" class="bgcp-toggle-redeem" data-code="<?php echo esc_attr( $card->code ); ?>"><?php esc_html_e( 'Redeem', 'bgcp' ); ?></a> |
										<?php endif; ?>
										<a href="#" class="bgcp-toggle-edit" data-code="<?php echo esc_attr( $card->code ); ?>"><?php esc_html_e( 'Edit', 'bgcp' ); ?></a> |
										<?php if ( 'active' === $card->status ) : ?>
											<a href="<?php echo esc_url( $this->status_action_url( $card->code, 'disabled' ) ); ?>" class="bgcp-confirm-disable"><?php esc_html_e( 'Disable', 'bgcp' ); ?></a>
										<?php else : ?>
											<a href="<?php echo esc_url( $this->status_action_url( $card->code, 'active' ) ); ?>"><?php esc_html_e( 'Re-enable', 'bgcp' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
								<?php if ( 'active' === $card->status && $card->balance > 0 ) : ?>
									<tr class="bgcp-redeem-row" data-code="<?php echo esc_attr( $card->code ); ?>" hidden>
										<td colspan="9">
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<?php wp_nonce_field( 'bgcp_redeem_card_' . $card->code ); ?>
												<input type="hidden" name="action" value="bgcp_redeem_card" />
												<input type="hidden" name="code" value="<?php echo esc_attr( $card->code ); ?>" />
												<label>
													<?php esc_html_e( 'Amount to redeem (£)', 'bgcp' ); ?>
													<input type="number" step="0.01" min="0.01" max="<?php echo esc_attr( $card->balance ); ?>" name="redeem_amount" required />
												</label>
												<?php submit_button( __( 'Redeem', 'bgcp' ), 'secondary small', '', false ); ?>
											</form>
										</td>
									</tr>
								<?php endif; ?>
								<tr class="bgcp-edit-row" data-code="<?php echo esc_attr( $card->code ); ?>" hidden>
									<td colspan="9">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( 'bgcp_edit_balance_' . $card->code ); ?>
											<input type="hidden" name="action" value="bgcp_edit_balance" />
											<input type="hidden" name="code" value="<?php echo esc_attr( $card->code ); ?>" />
											<label>
												<?php esc_html_e( 'New balance (£)', 'bgcp' ); ?>
												<input type="number" step="0.01" min="0" name="balance" value="<?php echo esc_attr( $card->balance ); ?>" required />
											</label>
											<?php submit_button( __( 'Save balance', 'bgcp' ), 'secondary small', '', false ); ?>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				</div>
				</section>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * "Cash" for manually-created cards (no order — phone/in-person sale,
	 * see handle_manual_create()); otherwise the order's actual payment
	 * method title (e.g. "Credit Card (Stripe)") so the two ways a card
	 * gets funded are distinguishable at a glance.
	 */
	public static function payment_method_label( $card ) {
		if ( ! $card->order_id ) {
			return __( 'Cash (manual)', 'bgcp' );
		}

		$order = wc_get_order( $card->order_id );
		if ( ! $order ) {
			return __( 'Unknown', 'bgcp' );
		}

		$title = $order->get_payment_method_title();

		return $title ? $title : __( 'Unknown', 'bgcp' );
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

			if ( is_wp_error( $code ) ) {
				$args = array(
					'bgcp_error' => rawurlencode( $code->get_error_message() ),
					'bgcp_view'  => 'create',
				);
			} else {
				if ( $send_email ) {
					$card = BGCP_DB::get_card_by_code( $code );
					do_action( 'bgcp_send_gift_card_email', $card );
				}
				$args = array( 'bgcp_notice' => 'created' );
			}
		} else {
			$args = array(
				'bgcp_error' => rawurlencode( __( 'Enter a valid amount (£1–£1000) and recipient email.', 'bgcp' ) ),
				'bgcp_view'  => 'create',
			);
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=product&page=bgcp-cards' ) ) );
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

	public function handle_edit_balance() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		check_admin_referer( 'bgcp_edit_balance_' . $code );

		$balance = isset( $_POST['balance'] ) ? wc_format_decimal( wp_unslash( $_POST['balance'] ) ) : null;

		if ( $code && null !== $balance && '' !== $balance && (float) $balance >= 0 ) {
			$result = BGCP_DB::set_balance( $code, $balance );
			$args   = is_wp_error( $result )
				? array( 'bgcp_error' => rawurlencode( $result->get_error_message() ) )
				: array( 'bgcp_notice' => 'balance_updated' );
		} else {
			$args = array( 'bgcp_error' => rawurlencode( __( 'Enter a valid balance.', 'bgcp' ) ) );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=product&page=bgcp-cards' ) ) );
		exit;
	}

	public function handle_redeem_card() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bgcp' ) );
		}

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		check_admin_referer( 'bgcp_redeem_card_' . $code );

		$amount = isset( $_POST['redeem_amount'] ) ? wc_format_decimal( wp_unslash( $_POST['redeem_amount'] ) ) : 0;

		if ( $code && $amount > 0 ) {
			$result = BGCP_DB::adjust_balance( $code, -$amount, 'manual in-person/phone redemption' );
			$args   = is_wp_error( $result )
				? array( 'bgcp_error' => rawurlencode( $result->get_error_message() ) )
				: array( 'bgcp_notice' => 'redeemed' );
		} else {
			$args = array( 'bgcp_error' => rawurlencode( __( 'Enter a valid redemption amount.', 'bgcp' ) ) );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=product&page=bgcp-cards' ) ) );
		exit;
	}
}
