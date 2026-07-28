<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "=== " . wp_strip_all_tags( $email_heading ) . " ===\n\n";

echo wp_strip_all_tags( $intro ) . "\n\n";

if ( ! empty( $card->recipient_name ) ) {
	echo sprintf( __( 'Hi %s,', 'bgcp' ), wp_strip_all_tags( $card->recipient_name ) ) . "\n\n";
}

if ( ! empty( $card->message ) ) {
	echo '"' . wp_strip_all_tags( $card->message ) . '"' . "\n\n";
}

echo __( 'Gift Card Code:', 'bgcp' ) . ' ' . wp_strip_all_tags( $card->code ) . "\n";
echo __( 'Amount:', 'bgcp' ) . ' ' . wp_strip_all_tags( wc_price( $card->initial_amount ) ) . "\n\n";

echo __( 'Redeem at checkout:', 'bgcp' ) . ' ' . $redeem_url . "\n";
echo __( 'Check your balance:', 'bgcp' ) . ' ' . $balance_url . "\n\n";

if ( ! empty( $card->expires_at ) ) {
	echo sprintf( __( 'Valid until %s.', 'bgcp' ), date_i18n( 'j F Y', strtotime( $card->expires_at ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
