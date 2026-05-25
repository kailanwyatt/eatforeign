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

	private const REWRITE_VERSION = '7';

	/**
	 * Public catalog post types and URL prefixes.
	 *
	 * @var array<string, string>
	 */
	private const CATALOG_SINGULAR_ROUTES = [
		'dishes'        => 'ef_dish',
		'celebrations'  => 'ef_celebration',
		'countries'     => 'ef_country',
		'restaurants'   => 'ef_restaurant',
	];

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
		add_action( 'template_redirect', [ self::class, 'resolve_catalog_singular_404' ], 1 );
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

		foreach ( self::CATALOG_SINGULAR_ROUTES as $prefix => $post_type ) {
			add_rewrite_rule(
				'^' . $prefix . '/([^/]+)/?$',
				'index.php?post_type=' . $post_type . '&name=$matches[1]',
				'top'
			);
		}

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

	/**
	 * Resolve catalog singles when rewrite rules miss (avoids 404 on /dishes/{slug}/ etc.).
	 */
	public static function resolve_catalog_singular_404(): void {
		if ( ! is_404() ) {
			return;
		}

		$path = Helpers::request_path();

		foreach ( self::CATALOG_SINGULAR_ROUTES as $prefix => $post_type ) {
			$pattern = '#^/' . preg_quote( $prefix, '#' ) . '/([^/]+)/?$#';

			if ( preg_match( $pattern, $path, $matches ) !== 1 ) {
				continue;
			}

			$slug = sanitize_title( $matches[1] );
			$post = get_page_by_path( $slug, OBJECT, $post_type );

			if ( ! $post instanceof \WP_Post || $post->post_status !== 'publish' ) {
				continue;
			}

			global $wp_query;

			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = (int) $post->ID;
			$wp_query->is_404            = false;
			$wp_query->is_single         = true;
			$wp_query->is_singular       = true;
			$wp_query->posts             = [ $post ];
			$wp_query->post              = $post;
			$wp_query->post_count        = 1;
			$wp_query->found_posts       = 1;
			$wp_query->max_num_pages     = 1;

			if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) ) {
				$GLOBALS['wp']->handle_404 = false;
			}

			status_header( 200 );
			nocache_headers();

			$GLOBALS['post'] = $post;
			setup_postdata( $post );

			$single_template = locate_template( 'single-' . $post_type . '.php' );

			if ( $single_template !== '' ) {
				load_template( $single_template );
				exit;
			}
		}
	}
}
