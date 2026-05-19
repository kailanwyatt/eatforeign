<?php
/**
 * Auth REST routes.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\REST;

use EatForeign\Repositories\PassportRepository;
use EatForeign\Support\AuthToken;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class AuthController {
	public static function register_routes(): void {
		register_rest_route(
			'eatforeign/v1',
			'/auth/register',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'register' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'eatforeign/v1',
			'/auth/login',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'login' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'eatforeign/v1',
			'/auth/logout',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'logout' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function register( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );
		$name     = sanitize_text_field( (string) $request->get_param( 'displayName' ) );

		if ( $email === '' || $password === '' ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Email and password are required.', [ 'status' => 400 ] );
		}

		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Password must be at least 8 characters.', [ 'status' => 400 ] );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'eatforeign_email_exists', 'An account with that email already exists.', [ 'status' => 409 ] );
		}

		$user_id = wp_create_user( $email, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_update_user(
			[
				'ID'            => $user_id,
				'display_name'  => $name !== '' ? $name : $email,
				'user_nicename' => sanitize_title( $name !== '' ? $name : strstr( $email, '@', true ) ),
			]
		);

		update_user_meta( $user_id, 'ef_display_name_override', $name );
		update_user_meta( $user_id, 'ef_profile_public', 1 );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		$token = AuthToken::issue_token( $user_id );

		return new WP_REST_Response(
			[
				'token' => $token,
				'user'  => PassportRepository::format_user_profile( get_user_by( 'id', $user_id ), true ),
			],
			201
		);
	}

	public static function login( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );

		if ( $email === '' || $password === '' ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Email and password are required.', [ 'status' => 400 ] );
		}

		$user = wp_authenticate( $email, $password );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );

		$token = AuthToken::issue_token( $user->ID );

		return new WP_REST_Response(
			[
				'token' => $token,
				'user'  => PassportRepository::format_user_profile( $user, true ),
			]
		);
	}

	public static function logout( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			AuthToken::revoke_token( $user_id );
		}

		wp_logout();

		return new WP_REST_Response( [ 'success' => true ] );
	}
}
