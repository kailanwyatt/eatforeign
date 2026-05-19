<?php
/**
 * REST bootstrap.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\REST;

final class REST {
	public static function register(): void {
		add_action( 'rest_api_init', [ AuthController::class, 'register_routes' ] );
		add_action( 'rest_api_init', [ BootstrapController::class, 'register_routes' ] );
		add_action( 'rest_api_init', [ AccountController::class, 'register_routes' ] );
		add_action( 'rest_api_init', [ SocialController::class, 'register_routes' ] );
	}
}
