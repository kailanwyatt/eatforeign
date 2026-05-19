<?php
/**
 * Register custom post types.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\PostTypes;

use EatForeign\Support\PostType;

final class PostTypes {
	public static function register(): void {
		self::register_celebration();
		self::register_dish();
		self::register_country();
		self::register_restaurant();
		self::register_celebration_post();
		self::register_comment();
	}

	private static function register_celebration(): void {
		register_post_type(
			PostType::CELEBRATION,
			[
				'labels'       => self::labels( 'Celebration', 'Celebrations' ),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => 'celebration',
				'graphql_plural_name' => 'celebrations',
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'celebrations', 'with_front' => false ],
			]
		);
	}

	private static function register_dish(): void {
		register_post_type(
			PostType::DISH,
			[
				'labels'       => self::labels( 'Dish', 'Dishes' ),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => 'dish',
				'graphql_plural_name' => 'dishes',
				'menu_icon'    => 'dashicons-carrot',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'dishes', 'with_front' => false ],
			]
		);
	}

	private static function register_country(): void {
		register_post_type(
			PostType::COUNTRY,
			[
				'labels'       => self::labels( 'Country', 'Countries' ),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => 'country',
				'graphql_plural_name' => 'countries',
				'menu_icon'    => 'dashicons-admin-site-alt3',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'countries', 'with_front' => false ],
			]
		);
	}

	private static function register_restaurant(): void {
		register_post_type(
			PostType::RESTAURANT,
			[
				'labels'       => self::labels( 'Restaurant', 'Restaurants' ),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => 'restaurant',
				'graphql_plural_name' => 'restaurants',
				'menu_icon'    => 'dashicons-store',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'restaurants', 'with_front' => false ],
			]
		);
	}

	private static function register_celebration_post(): void {
		register_post_type(
			PostType::CELEBRATION_POST,
			[
				'labels'       => self::labels( 'Celebration Post', 'Celebration Posts' ),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => 'celebrationPost',
				'graphql_plural_name' => 'celebrationPosts',
				'menu_icon'    => 'dashicons-camera',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'celebration-posts', 'with_front' => false ],
			]
		);
	}

	private static function register_comment(): void {
		register_post_type(
			PostType::COMMENT,
			[
				'labels'       => self::labels( 'Post Comment', 'Post Comments' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => 'celebrationPostComment',
				'graphql_plural_name' => 'celebrationPostComments',
				'menu_icon'    => 'dashicons-format-chat',
				'supports'     => [ 'editor', 'author', 'custom-fields' ],
				'has_archive'  => false,
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function labels( string $singular, string $plural ): array {
		return [
			'name'          => $plural,
			'singular_name' => $singular,
			'add_new_item'  => 'Add New ' . $singular,
			'edit_item'     => 'Edit ' . $singular,
			'new_item'      => 'New ' . $singular,
			'view_item'     => 'View ' . $singular,
			'search_items'  => 'Search ' . $plural,
			'not_found'     => 'No ' . strtolower( $plural ) . ' found',
		];
	}
}
