<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the gift card DB table and all read/write access to it.
 * A plain custom table rather than a CPT — gift cards are high-volume,
 * simple records and don't need post revisions/meta overhead.
 */
class BGCP_DB {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'bgcp_gift_cards';
	}

	public static function create_table() {
		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(32) NOT NULL,
			initial_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			balance DECIMAL(10,2) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			recipient_name VARCHAR(190) DEFAULT '',
			recipient_email VARCHAR(190) DEFAULT '',
			purchaser_email VARCHAR(190) DEFAULT '',
			message TEXT NULL,
			order_id BIGINT UNSIGNED DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY order_id (order_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Generate a unique, human-typeable code, e.g. GC-8H3K-9QRT
	 */
	public static function generate_code() {
		global $wpdb;
		$table = self::table_name();

		do {
			$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I ambiguity
			$part  = '';
			for ( $i = 0; $i < 8; $i++ ) {
				$part .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
			}
			$code   = 'GC-' . substr( $part, 0, 4 ) . '-' . substr( $part, 4, 4 );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) );
		} while ( $exists );

		return $code;
	}

	public static function create_card( array $args ) {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql' );

		$defaults = array(
			'code'            => self::generate_code(),
			'initial_amount'  => 0,
			'balance'         => 0,
			'status'          => 'active',
			'recipient_name'  => '',
			'recipient_email' => '',
			'purchaser_email' => '',
			'message'         => '',
			'order_id'        => null,
			'expires_at'      => null,
		);
		$data               = wp_parse_args( $args, $defaults );
		$data               = array_intersect_key( $data, $defaults );
		$data['balance']    = $data['initial_amount'];
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$inserted = $wpdb->insert(
			$table,
			$data,
			array( '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			error_log( 'BGCP: failed to insert gift card — ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return new WP_Error( 'bgcp_db_error', __( 'Could not create gift card.', 'bgcp' ) );
		}

		return $data['code'];
	}

	public static function get_card_by_code( $code ) {
		global $wpdb;
		$table = self::table_name();
		$code  = strtoupper( trim( $code ) );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ) );
	}

	public static function get_card( $id ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function get_cards( $args = array() ) {
		global $wpdb;
		$table = self::table_name();

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND (code LIKE %s OR recipient_email LIKE %s OR purchaser_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

		$sql      = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * All cards created on or after $since (mysql datetime) — used for the
	 * weekly/monthly sales report, not paginated since report windows are
	 * small relative to the full table.
	 */
	public static function get_cards_since( $since ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE created_at >= %s ORDER BY created_at ASC", $since ) );
	}

	/**
	 * Adjust balance by a delta (negative to redeem, positive to refund/top up).
	 * Returns the new balance, or WP_Error if it would go negative or the card is unusable.
	 *
	 * Done as a single atomic UPDATE (balance + delta checked in the WHERE
	 * clause, not read-then-written) so two simultaneous redemptions of the
	 * same code can't both succeed and double-spend the card.
	 */
	public static function adjust_balance( $code, $delta, $note = '' ) {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql' );
		$delta = round( (float) $delta, 2 );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET balance = ROUND( balance + %f, 2 ), updated_at = %s
				 WHERE code = %s AND status = 'active' AND ROUND( balance + %f, 2 ) >= 0
				 AND ( expires_at IS NULL OR expires_at > %s )",
				$delta,
				$now,
				$code,
				$delta,
				$now
			)
		);

		if ( ! $updated ) {
			// Nothing matched — work out why, for a useful error message.
			$card = self::get_card_by_code( $code );
			if ( ! $card ) {
				return new WP_Error( 'bgcp_not_found', __( 'Gift card not found.', 'bgcp' ) );
			}
			if ( 'active' !== $card->status ) {
				return new WP_Error( 'bgcp_inactive', __( 'This gift card is not active.', 'bgcp' ) );
			}
			if ( $card->expires_at && strtotime( $card->expires_at ) < time() ) {
				return new WP_Error( 'bgcp_expired', __( 'This gift card has expired.', 'bgcp' ) );
			}
			return new WP_Error( 'bgcp_insufficient', __( 'Insufficient gift card balance.', 'bgcp' ) );
		}

		$card = self::get_card_by_code( $code );

		do_action( 'bgcp_balance_adjusted', $card->id, $delta, $card->balance, $note );

		return (float) $card->balance;
	}

	/**
	 * Directly set a card's balance, bypassing the delta-only adjust_balance()
	 * checks (status/expiry) — for admin corrections of a mistaken amount,
	 * not for redemptions. Use adjust_balance() for anything redemption-like.
	 */
	public static function set_balance( $code, $new_balance ) {
		global $wpdb;
		$table = self::table_name();
		$card  = self::get_card_by_code( $code );

		if ( ! $card ) {
			return new WP_Error( 'bgcp_not_found', __( 'Gift card not found.', 'bgcp' ) );
		}

		$new_balance = round( max( 0, (float) $new_balance ), 2 );

		$updated = $wpdb->update(
			$table,
			array(
				'balance'    => $new_balance,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $card->id ),
			array( '%f', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			error_log( 'BGCP: failed to update gift card balance — ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return new WP_Error( 'bgcp_db_error', __( 'Could not update balance.', 'bgcp' ) );
		}

		do_action( 'bgcp_balance_adjusted', $card->id, $new_balance - (float) $card->balance, $new_balance, 'manual balance edit' );

		return $new_balance;
	}

	public static function set_status( $code, $status ) {
		global $wpdb;
		$table = self::table_name();
		$card  = self::get_card_by_code( $code );
		if ( ! $card ) {
			return false;
		}
		return $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $card->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
