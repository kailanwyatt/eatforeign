<?php
/**
 * Theme routes for auth and passport pages.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

final class Routes {
	private const QUERY_VAR = 'ef_page';

	private const REWRITE_VERSION = '6';

	/** @var list<string> */
	private const STATIC_ROUTES = [
		'login',
		'register',
		'account',
		'passport',
		'calendar',
		'directory',
		'countries',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'register_rewrites' ] );
		add_filter( 'query_vars', [ self::class, 'register_query_vars' ] );
		add_filter( 'template_include', [ self::class, 'template_include' ], 99 );
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = 'ef_passport_slug';
		$vars[] = 'ef_dish_slug';
		$vars[] = 'ef_account_tab';

		return $vars;
	}

	public static function register_rewrites(): void {
		add_rewrite_rule(
			'^account/(profile|passport|notifications|security)/?$',
			'index.php?' . self::QUERY_VAR . '=account&ef_account_tab=$matches[1]',
			'top'
		);

		foreach ( self::STATIC_ROUTES as $route ) {
			add_rewrite_rule(
				'^' . $route . '/page/([0-9]{1,})/?$',
				'index.php?' . self::QUERY_VAR . '=' . $route . '&paged=$matches[1]',
				'top'
			);
			add_rewrite_rule(
				'^' . $route . '/?$',
				'index.php?' . self::QUERY_VAR . '=' . $route,
				'top'
			);
		}

		add_rewrite_rule(
			'^passport/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=passport-detail&ef_passport_slug=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^dishes/([^/]+)/passport/?$',
			'index.php?' . self::QUERY_VAR . '=dish-passport&ef_dish_slug=$matches[1]',
			'top'
		);

		$stored = get_option( 'eatforeign_theme_rewrite_ver' );

		if ( $stored !== self::REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'eatforeign_theme_rewrite_ver', self::REWRITE_VERSION );
		}
	}

	public static function template_include( string $template ): string {
		if ( is_admin() || is_customize_preview() || wp_doing_ajax() ) {
			return $template;
		}

		$page = get_query_var( self::QUERY_VAR );

		if (! is_string( $page ) || $page === '' ) {
			return $template;
		}

		$map = [
			'login'           => 'page-login.php',
			'register'        => 'page-register.php',
			'account'         => 'page-account.php',
			'passport'        => 'page-passport.php',
			'passport-detail' => 'page-passport-detail.php',
			'calendar'        => 'page-calendar.php',
			'directory'       => 'page-directory.php',
			'countries'       => 'page-countries.php',
			'dish-passport'   => 'page-dish-passport.php',
		];

		if (! isset( $map[ $page ] ) ) {
			return $template;
		}

		$path = get_template_directory() . '/' . $map[ $page ];

		return file_exists( $path ) ? $path : $template;
	}
}
