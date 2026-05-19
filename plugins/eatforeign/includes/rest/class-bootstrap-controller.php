<?php
/**
 * Bootstrap REST route.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\REST;

use EatForeign\Repositories\PassportRepository;
use WP_REST_Request;
use WP_REST_Response;

final class BootstrapController {
	public static function register_routes(): void {
		register_rest_route(
			'eatforeign/v1',
			'/bootstrap',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'bootstrap' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function bootstrap( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();

		return new WP_REST_Response(
			[
				'authenticated' => $user_id > 0,
				'user'          => $user_id > 0 ? PassportRepository::format_user_profile( get_user_by( 'id', $user_id ) ) : null,
				'locationLabel' => $user_id > 0 ? (string) get_user_meta( $user_id, 'ef_preferred_location_label', true ) : '',
				'features'      => [
					'graphql' => class_exists( 'WPGraphQL' ),
					'rest'    => true,
				],
			]
		);
	}
}
