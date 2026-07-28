<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

/**
 * The gift card email itself. Uses the standard WooCommerce email
 * template system so it inherits the site's header/footer, and can be
 * overridden the normal WC way by copying the templates into the theme.
 */
class BGCP_Email_Gift_Card extends WC_Email {

	public function __construct() {
		$this->id             = 'bgcp_gift_card';
		$this->title          = __( 'Gift Card', 'bgcp' );
		$this->description    = __( 'Sent to the recipient when a gift card is purchased (or on the requested delivery date).', 'bgcp' );
		$this->customer_email = true;
		$this->template_html  = 'emails/customer-gift-card.php';
		$this->template_plain = 'emails/plain/customer-gift-card.php';
		$this->template_base  = BGCP_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{gift_card_code}' => '',
		);

		add_action( 'bgcp_send_gift_card_email', array( $this, 'trigger' ) );

		parent::__construct();
	}

	public function trigger( $card ) {
		if ( ! $card || empty( $card->recipient_email ) ) {
			return;
		}

		$this->object                           = $card;
		$this->recipient                        = $card->recipient_email;
		$this->placeholders['{gift_card_code}'] = $card->code;

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);
	}

	public function get_default_subject() {
		return __( "You've received a Gift Card!", 'bgcp' );
	}

	public function get_default_heading() {
		return __( 'Your Gift Card', 'bgcp' );
	}

	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'card'          => $this->object,
				'email_heading' => $this->get_heading(),
				'intro'         => BGCP_Settings::get_email_intro(),
				'image_url'     => BGCP_Settings::get_image_url( 'large' ),
				'balance_url'   => $this->get_balance_page_url(),
				'redeem_url'    => $this->get_redeem_url(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'card'          => $this->object,
				'email_heading' => $this->get_heading(),
				'intro'         => BGCP_Settings::get_email_intro(),
				'balance_url'   => $this->get_balance_page_url(),
				'redeem_url'    => $this->get_redeem_url(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	private function get_balance_page_url() {
		$page_id = get_option( 'bgcp_balance_page_id' );
		return $page_id ? get_permalink( $page_id ) : home_url( '/' );
	}

	private function get_redeem_url() {
		return wc_get_cart_url();
	}
}
