<?php
/**
 * Theme setup and assets.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

final class Theme {
	public static function init(): void {
		Nav::register();
		add_action( 'after_setup_theme', [ self::class, 'setup' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'wp_head', [ self::class, 'render_favicon' ], 5 );
		add_action( 'after_switch_theme', [ self::class, 'activate' ] );
		add_filter( 'body_class', [ self::class, 'body_class' ] );
	}

	public static function setup(): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );

		register_nav_menus(
			[
				'primary'  => __( 'Primary Menu', 'eatforeign' ),
				'footer-1' => __( 'Footer Menu 1', 'eatforeign' ),
				'footer-2' => __( 'Footer Menu 2', 'eatforeign' ),
				'footer-3' => __( 'Footer Menu 3', 'eatforeign' ),
			]
		);
	}

	public static function activate(): void {
		Routes::register_rewrites();
		flush_rewrite_rules();
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'eatforeign-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'eatforeign-theme',
			get_template_directory_uri() . '/assets/theme.css',
			[ 'eatforeign-fonts' ],
			self::asset_version( 'theme.css' )
		);

		if ( Helpers::current_ef_route() === 'dish-passport' ) {
			wp_enqueue_script(
				'eatforeign-passport-wizard',
				get_template_directory_uri() . '/assets/passport-wizard.js',
				[],
				self::asset_version( 'passport-wizard.js' ),
				true
			);
		}

		if ( is_singular( 'ef_celebration' ) ) {
			wp_enqueue_script(
				'eatforeign-celebration-participate',
				get_template_directory_uri() . '/assets/celebration-participate.js',
				[],
				self::asset_version( 'celebration-participate.js' ),
				true
			);
		}

		if ( Helpers::current_ef_route() === 'calendar' ) {
			wp_enqueue_script(
				'eatforeign-calendar',
				get_template_directory_uri() . '/assets/calendar.js',
				[],
				self::asset_version( 'calendar.js' ),
				true
			);
		}
	}

	public static function render_favicon(): void {
		$icon = get_template_directory_uri() . '/assets/favicon.svg';

		if (! file_exists( get_template_directory() . '/assets/favicon.svg' ) ) {
			return;
		}

		printf(
			'<link rel="icon" type="image/svg+xml" href="%s" />' . "\n",
			esc_url( $icon )
		);
	}

	private static function asset_version( string $filename ): string {
		$path = get_template_directory() . '/assets/' . $filename;

		return file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';
	}

	/**
	 * @param list<string> $classes
	 * @return list<string>
	 */
	public static function body_class( array $classes ): array {
		$route = Helpers::current_ef_route();

		if ( $route !== '' ) {
			$classes[] = 'ef-route-' . sanitize_html_class( str_replace( '_', '-', $route ) );
		}

		return $classes;
	}
}
