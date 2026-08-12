<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly/monthly gift card sales report — a CSV of every card created in
 * the period, emailed to the address set on the Gift Card Settings page.
 * Driven by WP-Cron; if the site's cron is disabled/offloaded, the
 * scheduled event still needs firing by whatever real cron runner is in
 * use (see README), same caveat as the scheduled gift card delivery in
 * BGCP_Order.
 */
class BGCP_Report {

	const CRON_HOOK = 'bgcp_send_sales_report';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_report' ) );
	}

	public static function register_schedules( $schedules ) {
		$schedules['bgcp_weekly']  = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly (Gift Card Report)', 'bgcp' ),
		);
		$schedules['bgcp_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once Monthly (Gift Card Report)', 'bgcp' ),
		);
		return $schedules;
	}

	/**
	 * Brings the scheduled cron event in line with current settings.
	 * Called after Settings are saved; also safe to call any time.
	 */
	public static function reschedule() {
		$frequency = BGCP_Settings::get_report_frequency();
		$email     = BGCP_Settings::get_report_email();
		$current   = wp_get_schedule( self::CRON_HOOK );

		if ( ! $frequency || ! is_email( $email ) ) {
			if ( $current ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}

		$target = 'bgcp_' . $frequency;

		if ( $current !== $target ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_schedule_event( time(), $target, self::CRON_HOOK );
		}
	}

	public static function send_report() {
		$email = BGCP_Settings::get_report_email();
		if ( ! is_email( $email ) ) {
			return;
		}

		$frequency = BGCP_Settings::get_report_frequency();
		$days      = 'monthly' === $frequency ? 30 : 7;
		$since     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$cards = BGCP_DB::get_cards_since( $since );
		$file  = self::build_csv( $cards );

		if ( ! $file ) {
			error_log( 'BGCP: could not build gift card sales report CSV' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Gift card sales report — %s', 'bgcp' ),
			get_bloginfo( 'name' )
		);
		$body    = sprintf(
			/* translators: 1: number of gift cards, 2: number of days covered */
			__( '%1$d gift card(s) created in the last %2$d days. CSV attached.', 'bgcp' ),
			count( $cards ),
			$days
		);

		$sent = wp_mail( $email, $subject, $body, array(), array( $file ) );

		if ( ! $sent ) {
			error_log( 'BGCP: failed to send gift card sales report to ' . $email ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}

		wp_delete_file( $file );
	}

	private static function build_csv( array $cards ) {
		$file = wp_tempnam( 'bgcp-sales-report' );
		if ( ! $file ) {
			return false;
		}

		$handle = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}

		fputcsv(
			$handle,
			array(
				__( 'Code', 'bgcp' ),
				__( 'Status', 'bgcp' ),
				__( 'Initial Amount', 'bgcp' ),
				__( 'Balance', 'bgcp' ),
				__( 'Recipient Name', 'bgcp' ),
				__( 'Recipient Email', 'bgcp' ),
				__( 'Purchaser Email', 'bgcp' ),
				__( 'Message', 'bgcp' ),
				__( 'Order ID', 'bgcp' ),
				__( 'Paid Via', 'bgcp' ),
				__( 'Expires At', 'bgcp' ),
				__( 'Created At', 'bgcp' ),
				__( 'Updated At', 'bgcp' ),
			)
		);

		foreach ( $cards as $card ) {
			fputcsv(
				$handle,
				array(
					$card->code,
					$card->status,
					$card->initial_amount,
					$card->balance,
					$card->recipient_name,
					$card->recipient_email,
					$card->purchaser_email,
					$card->message,
					$card->order_id,
					BGCP_Admin::payment_method_label( $card ),
					$card->expires_at,
					$card->created_at,
					$card->updated_at,
				)
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $file;
	}
}
