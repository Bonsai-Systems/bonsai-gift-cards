<?php
/**
 * Gift card HTML email.
 * Override by copying to yourtheme/woocommerce/emails/customer-gift-card.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php echo esc_html( $intro ); ?></p>

<?php if ( ! empty( $card->recipient_name ) ) : ?>
	<p><?php printf( esc_html__( 'Hi %s,', 'bgcp' ), esc_html( $card->recipient_name ) ); ?></p>
<?php endif; ?>

<?php if ( ! empty( $card->message ) ) : ?>
	<blockquote style="margin:20px 0;padding:15px 20px;border-left:4px solid #ee4367;background:#e2ecf3;font-style:italic;">
		<?php echo wp_kses_post( nl2br( esc_html( $card->message ) ) ); ?>
	</blockquote>
<?php endif; ?>

<?php if ( ! empty( $image_url ) ) : ?>
	<p style="text-align:center;margin:24px 0;">
		<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Gift Card', 'bgcp' ); ?>" style="max-width:100%;height:auto;border-radius:8px;" />
	</p>
<?php endif; ?>

<table cellspacing="0" cellpadding="0" style="width:100%;margin:20px 0;text-align:center;">
	<tr>
		<td style="padding:20px;background:#000000;border-radius:8px;">
			<p style="color:#ffffff;margin:0 0 6px;font-size:14px;letter-spacing:1px;text-transform:uppercase;"><?php esc_html_e( 'Gift Card Code', 'bgcp' ); ?></p>
			<p style="color:#ee4367;margin:0;font-size:28px;font-weight:bold;letter-spacing:2px;"><?php echo esc_html( $card->code ); ?></p>
			<p style="color:#ffffff;margin:10px 0 0;font-size:18px;"><?php echo wp_kses_post( wc_price( $card->initial_amount ) ); ?></p>
		</td>
	</tr>
</table>

<p style="text-align:center;">
	<a href="<?php echo esc_url( $redeem_url ); ?>" style="display:inline-block;padding:12px 28px;background:#ee4367;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">
		<?php esc_html_e( 'Redeem This Gift Card', 'bgcp' ); ?>
	</a>
</p>

<p><?php esc_html_e( 'Enter this code at checkout to redeem the balance against your order.', 'bgcp' ); ?></p>

<?php if ( ! empty( $card->expires_at ) ) : ?>
	<p style="color:#666;font-size:13px;">
		<?php printf( esc_html__( 'Valid until %s.', 'bgcp' ), esc_html( date_i18n( 'j F Y', strtotime( $card->expires_at ) ) ) ); ?>
	</p>
<?php endif; ?>

<p><?php esc_html_e( 'You can check the remaining balance at any time here:', 'bgcp' ); ?> <a href="<?php echo esc_url( $balance_url ); ?>"><?php echo esc_html( $balance_url ); ?></a></p>

<?php
do_action( 'woocommerce_email_footer', $email );
