<?php
/**
 * API token authentication for mobile and headless clients.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class AuthToken {
	private const META_KEY = 'ef_api_token';

	public static function register(): void {
		add_filter( 'determine_current_user', [ self::class, 'authenticate_from_header' ], 20 );
		add_action( 'init', [ self::class, 'send_cors_headers' ], 0 );
	}

	public static function send_cors_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_ORIGIN'] ) ) : '*';

		if ( $origin === '' ) {
			$origin = '*';
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-EatForeign-Token' );
		header( 'Access-Control-Allow-Credentials: true' );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
			status_header( 204 );
			exit;
		}
	}

	/**
	 * @param int|false $user_id
	 * @return int|false
	 */
	public static function authenticate_from_header( $user_id ) {
		if ( is_numeric( $user_id ) && (int) $user_id > 0 ) {
			return $user_id;
		}

		$token = self::read_token_from_request();
		if ( $token === '' ) {
			return $user_id;
		}

		$users = get_users(
			[
				'meta_key'   => self::META_KEY,
				'meta_value' => $token,
				'number'     => 1,
				'fields'     => 'ID',
			]
		);

		if ( $users === [] ) {
			return $user_id;
		}

		return (int) $users[0];
	}

	public static function issue_token( int $user_id ): string {
		$token = wp_generate_password( 48, false, false );
		update_user_meta( $user_id, self::META_KEY, $token );

		return $token;
	}

	public static function revoke_token( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	private static function read_token_from_request(): string {
		if ( isset( $_SERVER['HTTP_X_EATFOREIGN_TOKEN'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_EATFOREIGN_TOKEN'] ) );
		}

		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_AUTHORIZATION'] ) );
			if ( str_starts_with( strtolower( $header ), 'bearer ' ) ) {
				return trim( substr( $header, 7 ) );
			}
		}

		return '';
	}
}
