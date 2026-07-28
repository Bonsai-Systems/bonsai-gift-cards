<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST routes backing the Blocks checkout "Have a gift card?" field
 * (assets/js/checkout-block.js). Thin wrappers around BGCP_Cart's static
 * methods — no business logic lives here.
 */
class BGCP_REST {

	const NS = 'bgcp/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$code_args = array(
			'code' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		register_rest_route(
			self::NS,
			'/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'apply' ),
				'permission_callback' => array( __CLASS__, 'verify_nonce' ),
				'args'                => $code_args,
			)
		);

		register_rest_route(
			self::NS,
			'/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'remove' ),
				'permission_callback' => array( __CLASS__, 'verify_nonce' ),
				'args'                => $code_args,
			)
		);

		register_rest_route(
			self::NS,
			'/applied',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'applied' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/balance',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'balance' ),
				'permission_callback' => array( __CLASS__, 'rate_limited_public' ),
				'args'                => $code_args,
			)
		);
	}

	/**
	 * State-changing cart routes (apply/remove) require a valid wp_rest
	 * nonce — checkout-block.js sends it via the X-WP-Nonce header.
	 */
	public static function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'bgcp_bad_nonce', __( 'Security check failed, please refresh and try again.', 'bgcp' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * /balance is deliberately public (self-serve lookups), but throttled
	 * per IP to make scripted code-guessing impractical.
	 */
	public static function rate_limited_public( WP_REST_Request $request ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'bgcp_balance_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );

		if ( $hits >= 20 ) {
			return new WP_Error( 'bgcp_rate_limited', __( 'Too many requests, please try again shortly.', 'bgcp' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function ensure_cart_session() {
		if ( function_exists( 'WC' ) && WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	public static function apply( WP_REST_Request $request ) {
		try {
			self::ensure_cart_session();

			$result = BGCP_Cart::apply_code( $request->get_param( 'code' ) );
			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 ); // phpcs:ignore
			}

			return new WP_REST_Response( array( 'success' => true, 'cards' => $result ), 200 );
		} catch ( Exception $e ) {
			error_log( 'BGCP REST apply() error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Something went wrong. Please try again.', 'bgcp' ) ), 500 );
		}
	}

	public static function remove( WP_REST_Request $request ) {
		try {
			$cards = BGCP_Cart::remove_code( $request->get_param( 'code' ) );
			return new WP_REST_Response( array( 'success' => true, 'cards' => $cards ), 200 );
		} catch ( Exception $e ) {
			error_log( 'BGCP REST remove() error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Something went wrong. Please try again.', 'bgcp' ) ), 500 );
		}
	}

	public static function applied() {
		return new WP_REST_Response( array( 'success' => true, 'cards' => BGCP_Cart::get_applied_cards() ), 200 );
	}

	public static function balance( WP_REST_Request $request ) {
		$card = BGCP_DB::get_card_by_code( $request->get_param( 'code' ) );

		if ( ! $card ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( "We couldn't find a gift card with that code.", 'bgcp' ) ), 404 ); // phpcs:ignore
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'balance' => (float) $card->balance,
				'status'  => $card->status,
				'expires' => $card->expires_at,
			),
			200
		);
	}
}
