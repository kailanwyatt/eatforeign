<?php
/**
 * Theme rendering helpers.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

use WP_Post;

final class Template {
	/**
	 * @param list<WP_Post> $posts
	 */
	public static function section( string $title, array $posts, string $card, ?string $description = null, ?string $anchor_id = null ): void {
		if ( $posts === [] ) {
			return;
		}

		ob_start();
		self::card_grid( $posts, $card );
		$content = ob_get_clean();

		if (! is_string( $content ) || $content === '' ) {
			return;
		}

		get_template_part(
			'template-parts/section',
			null,
			[
				'title'       => $title,
				'description' => $description,
				'content'     => $content,
				'anchor_id'   => $anchor_id,
			]
		);
	}

	/**
	 * @param list<WP_Post> $posts
	 */
	public static function dish_directory_grid( array $posts ): void {
		if ( $posts === [] ) {
			return;
		}

		echo '<div class="ef-grid ef-grid--directory">';

		foreach ( $posts as $post ) {
			get_template_part( 'template-parts/card', 'dish', [ 'post' => $post, 'layout' => 'directory' ] );
		}

		echo '</div>';
	}

	/**
	 * @param list<WP_Post> $posts
	 */
	public static function card_grid( array $posts, string $card ): void {
		if ( $posts === [] ) {
			return;
		}

		echo '<div class="ef-grid">';

		foreach ( $posts as $post ) {
			get_template_part( 'template-parts/card', $card, [ 'post' => $post ] );
		}

		echo '</div>';
	}

	public static function panel( string $title, string $content ): void {
		if (! Data::has_text( wp_strip_all_tags( $content ) ) ) {
			return;
		}

		get_template_part(
			'template-parts/panel',
			null,
			[
				'title'   => $title,
				'content' => $content,
			]
		);
	}
}
