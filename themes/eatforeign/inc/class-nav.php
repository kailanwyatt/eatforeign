<?php
/**
 * Primary navigation (WordPress menu + theme fallbacks).
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

final class Nav {
	public static function register(): void {
		add_filter( 'nav_menu_css_class', [ self::class, 'primary_menu_item_classes' ], 10, 4 );
	}

	/**
	 * @param list<string> $classes
	 * @return list<string>
	 */
	public static function primary_menu_item_classes( array $classes, $item, $args, int $depth ): array {
		if ( $depth > 0 || ! isset( $args->theme_location ) || $args->theme_location !== 'primary' ) {
			return $classes;
		}

		if ( self::is_menu_item_active( $item ) ) {
			$classes[] = 'is-active';
		}

		return $classes;
	}

	public static function render_primary(): void {
		wp_nav_menu(
			[
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'ef-nav__list',
				'fallback_cb'    => [ self::class, 'render_primary_fallback' ],
				'depth'          => 1,
			]
		);
	}

	public static function render_primary_fallback(): void {
		$route        = Helpers::current_ef_route();
		$today_active = Helpers::is_today_nav_active();
		?>
		<ul class="ef-nav__list">
			<li class="<?php echo $today_active ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Today', 'eatforeign' ); ?></a>
			</li>
			<li class="<?php echo $route === 'calendar' ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( home_url( '/calendar' ) ); ?>"><?php esc_html_e( 'Calendar', 'eatforeign' ); ?></a>
			</li>
			<li class="<?php echo $route === 'directory' ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( home_url( '/directory' ) ); ?>"><?php esc_html_e( 'Directory', 'eatforeign' ); ?></a>
			</li>
			<li class="<?php echo $route === 'passport' || $route === 'passport-detail' ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( home_url( '/passport' ) ); ?>"><?php esc_html_e( 'Passport', 'eatforeign' ); ?></a>
			</li>
			<li>
				<a href="<?php echo esc_url( home_url( '/#explore-by-country' ) ); ?>"><?php esc_html_e( 'Explore', 'eatforeign' ); ?></a>
			</li>
		</ul>
		<?php
	}

	private static function is_menu_item_active( $item ): bool {
		if ( ! $item instanceof \WP_Post ) {
			return false;
		}

		$item_path    = self::menu_item_path( $item );
		$request_path = Helpers::request_path();

		if ( $item_path === '/' ) {
			$fragment = (string) wp_parse_url( (string) $item->url, PHP_URL_FRAGMENT );

			if ( $fragment !== '' ) {
				return false;
			}

			return Helpers::is_today_nav_active();
		}

		if ( $request_path === $item_path ) {
			return true;
		}

		if ( $item_path !== '/' && str_starts_with( $request_path, $item_path . '/' ) ) {
			return true;
		}

		return false;
	}

	private static function menu_item_path( \WP_Post $item ): string {
		$url = '';

		if ( $item->type === 'post_type' && $item->object_id ) {
			$permalink = get_permalink( (int) $item->object_id );
			$url       = is_string( $permalink ) ? $permalink : '';
		} elseif ( $item->url !== '' ) {
			$url = (string) $item->url;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );

		return $path === '' ? '/' : $path;
	}
}
