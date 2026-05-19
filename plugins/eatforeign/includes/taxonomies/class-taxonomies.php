<?php
/**
 * Register taxonomies.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Taxonomies;

use EatForeign\Support\PostType;

final class Taxonomies {
	public static function register(): void {
		$shared = [ PostType::CELEBRATION, PostType::DISH, PostType::RESTAURANT, PostType::CELEBRATION_POST ];

		self::register_taxonomy( 'ef_cuisine', 'Cuisine', 'Cuisines', $shared );
		self::register_taxonomy( 'ef_country', 'Country', 'Countries', $shared );
		self::register_taxonomy( 'ef_celebration_type', 'Celebration Type', 'Celebration Types', [ PostType::CELEBRATION ] );
		self::register_taxonomy( 'ef_dish_type', 'Dish Type', 'Dish Types', [ PostType::DISH ] );
		self::register_taxonomy( 'ef_dietary_type', 'Dietary Type', 'Dietary Types', [ PostType::DISH, PostType::RESTAURANT ] );
		self::register_taxonomy( 'ef_spice_level', 'Spice Level', 'Spice Levels', [ PostType::DISH ] );
	}

	/**
	 * @param list<string> $object_types
	 */
	private static function register_taxonomy( string $taxonomy, string $singular, string $plural, array $object_types ): void {
		register_taxonomy(
			$taxonomy,
			$object_types,
			[
				'labels'            => [
					'name'          => $plural,
					'singular_name' => $singular,
				],
				'public'            => true,
				'show_in_rest'        => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => lcfirst( str_replace( ' ', '', $singular ) ),
				'graphql_plural_name' => lcfirst( str_replace( ' ', '', $plural ) ),
				'hierarchical'        => false,
				'rewrite'             => [ 'slug' => str_replace( '_', '-', $taxonomy ) ],
			]
		);
	}
}
