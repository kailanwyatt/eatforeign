<?php
/**
 * Account REST routes.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\REST;

use EatForeign\Repositories\CommunityRepository;
use EatForeign\Repositories\PassportRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class AccountController {
	public static function register_routes(): void {
		register_rest_route(
			'eatforeign/v1',
			'/account/profile',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ self::class, 'get_profile' ],
					'permission_callback' => [ self::class, 'require_user' ],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ self::class, 'update_profile' ],
					'permission_callback' => [ self::class, 'require_user' ],
				],
			]
		);

		register_rest_route(
			'eatforeign/v1',
			'/passports',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'list_passports' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'eatforeign/v1',
			'/passports/(?P<slug>[a-z0-9-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_passport' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function require_user(): bool {
		return get_current_user_id() > 0;
	}

	public static function get_profile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = get_user_by( 'id', get_current_user_id() );

		if (! $user ) {
			return new WP_Error( 'eatforeign_not_found', 'User not found.', [ 'status' => 404 ] );
		}

		$profile = PassportRepository::format_user_profile( $user, true );

		if ( $profile === null ) {
			return new WP_Error( 'eatforeign_not_found', 'Profile not found.', [ 'status' => 404 ] );
		}

		return new WP_REST_Response(
			[
				'profile'       => $profile,
				'locationLabel' => (string) get_user_meta( $user->ID, 'ef_preferred_location_label', true ),
			]
		);
	}

	public static function update_profile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$input = [
			'displayName'   => $request->get_param( 'displayName' ),
			'homeCity'      => $request->get_param( 'homeCity' ),
			'bio'           => $request->get_param( 'bio' ),
			'locationLabel' => $request->get_param( 'locationLabel' ),
		];

		$profile = CommunityRepository::update_profile( get_current_user_id(), array_filter( $input, static fn ( mixed $value ): bool => $value !== null ) );

		return new WP_REST_Response(
			[
				'profile'       => $profile,
				'locationLabel' => (string) get_user_meta( get_current_user_id(), 'ef_preferred_location_label', true ),
			]
		);
	}

	public static function list_passports( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'passports' => PassportRepository::get_public_passports(),
			]
		);
	}

	public static function get_passport( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug    = sanitize_title( (string) $request->get_param( 'slug' ) );
		$profile = PassportRepository::get_by_slug( $slug, get_current_user_id() );

		if ( $profile === null ) {
			return new WP_Error( 'eatforeign_not_found', 'Passport not found.', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( [ 'passport' => $profile ] );
	}
}
