<?php
/**
 * GraphQL read operations.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\GraphQL;

use EatForeign\Repositories\CatalogRepository;
use EatForeign\Repositories\CommunityRepository;
use EatForeign\Repositories\PassportRepository;
use EatForeign\Support\PostType;

final class Queries {
	public static function register(): void {
		register_graphql_field(
			'RootQuery',
			'catalogCelebrations',
			[
				'type'    => [ 'list_of' => 'EatForeignCelebration' ],
				'resolve' => static fn (): array => array_map(
					[ self::class, 'map_celebration' ],
					CatalogRepository::query_posts(
						PostType::CELEBRATION,
						[
							'posts_per_page' => 200,
							'meta_key'       => 'ef_event_date',
							'orderby'        => 'meta_value',
							'order'          => 'ASC',
						]
					)
				),
			]
		);

		register_graphql_field(
			'RootQuery',
			'catalogCountries',
			[
				'type'    => [ 'list_of' => 'EatForeignCountry' ],
				'resolve' => static fn (): array => array_map(
					[ self::class, 'map_country' ],
					CatalogRepository::query_posts(
						PostType::COUNTRY,
						[
							'posts_per_page' => 100,
						]
					)
				),
			]
		);

		register_graphql_field(
			'RootQuery',
			'countryBySlug',
			[
				'type'    => 'EatForeignCountry',
				'args'    => [
					'slug' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve' => static function ( $root, array $args ): ?array {
					$post = CatalogRepository::get_by_slug( PostType::COUNTRY, (string) $args['slug'] );
					return $post ? self::map_country( $post ) : null;
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'todayCelebrations',
			[
				'type'    => [ 'list_of' => 'EatForeignCelebration' ],
				'resolve' => static fn (): array => array_map( [ self::class, 'map_celebration' ], CatalogRepository::get_today_celebrations() ),
			]
		);

		register_graphql_field(
			'RootQuery',
			'celebrationBySlug',
			[
				'type'    => 'EatForeignCelebration',
				'args'    => [
					'slug' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve' => static function ( $root, array $args ): ?array {
					$post = CatalogRepository::get_by_slug( PostType::CELEBRATION, (string) $args['slug'] );
					return $post ? self::map_celebration( $post ) : null;
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'dishBySlug',
			[
				'type'    => 'EatForeignDish',
				'args'    => [
					'slug' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve' => static function ( $root, array $args ): ?array {
					$post = CatalogRepository::get_by_slug( PostType::DISH, (string) $args['slug'] );
					return $post ? self::map_dish( $post ) : null;
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'directoryDishes',
			[
				'type'    => [ 'list_of' => 'EatForeignDish' ],
				'args'    => [
					'cuisine'     => [ 'type' => 'String' ],
					'countrySlug' => [ 'type' => 'String' ],
					'dishType'    => [ 'type' => 'String' ],
					'query'       => [ 'type' => 'String' ],
				],
				'resolve' => static function ( $root, array $args ): array {
					return array_map(
						[ self::class, 'map_dish' ],
						CatalogRepository::filter_directory_dishes( $args )
					);
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'passports',
			[
				'type'    => [ 'list_of' => 'EatForeignPassport' ],
				'resolve' => static fn (): array => PassportRepository::get_all_passports(),
			]
		);

		register_graphql_field(
			'RootQuery',
			'celebrationPosts',
			[
				'type'    => [ 'list_of' => 'EatForeignCelebrationPost' ],
				'args'    => [
					'celebrationSlug' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve' => static function ( $root, array $args ): array {
					$post = CatalogRepository::get_by_slug( PostType::CELEBRATION, (string) $args['celebrationSlug'] );
					if ( ! $post ) {
						return [];
					}

					return array_map(
						[ self::class, 'map_celebration_post' ],
						CommunityRepository::get_posts_for_celebration( $post->ID )
					);
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'nearbyRestaurants',
			[
				'type'    => [ 'list_of' => 'EatForeignNearbyRestaurant' ],
				'args'    => [
					'dish'     => [ 'type' => [ 'non_null' => 'String' ] ],
					'location' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve' => static function ( $root, array $args ): array {
					if ( ! class_exists( '\EatForeignAPI\PlacesClient' ) ) {
						return [];
					}

					$results = \EatForeignAPI\PlacesClient::get_restaurants(
						(string) $args['dish'],
						(string) $args['location']
					);

					return array_map(
						static fn ( array $place ): array => [
							'id'      => (string) ( $place['id'] ?? '' ),
							'name'    => (string) ( $place['name'] ?? '' ),
							'address' => (string) ( $place['address'] ?? '' ),
							'lat'     => (float) ( $place['lat'] ?? 0 ),
							'lng'     => (float) ( $place['lng'] ?? 0 ),
							'website' => (string) ( $place['website'] ?? '' ),
							'rating'  => (float) ( $place['rating'] ?? 0 ),
						],
						$results
					);
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'passportBySlug',
			[
				'type'    => 'EatForeignPassport',
				'args'    => [
					'slug' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve' => static function ( $root, array $args ): ?array {
					return PassportRepository::get_by_slug( (string) $args['slug'] );
				},
			]
		);
	}

	/**
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	public static function map_celebration( $post ): array {
		$country_terms = wp_get_post_terms( $post->ID, 'ef_country' );
		$type_terms    = wp_get_post_terms( $post->ID, 'ef_celebration_type' );

		return [
			'id'                => $post->ID,
			'slug'              => $post->post_name,
			'title'             => get_the_title( $post ),
			'eventDate'         => (string) get_post_meta( $post->ID, 'ef_event_date', true ),
			'recurringRule'     => (string) get_post_meta( $post->ID, 'ef_recurring_rule', true ),
			'shortDescription'  => (string) get_post_meta( $post->ID, 'ef_short_description', true ),
			'longDescription'   => (string) get_post_meta( $post->ID, 'ef_long_description', true ),
			'country'           => ( function() use ( $country_terms ) {
				if ( ! is_wp_error( $country_terms ) && isset( $country_terms[0] ) ) {
					$name = $country_terms[0]->name;
					$flag = get_term_meta( $country_terms[0]->term_id, 'ef_flag_emoji', true );
					return $flag ? $flag . ' ' . $name : $name;
				}
				return '';
			} )(),
			'countrySlug'       => ( ! is_wp_error( $country_terms ) && isset( $country_terms[0] ) ) ? $country_terms[0]->slug : '',
			'celebrationType'   => ( ! is_wp_error( $type_terms ) && isset( $type_terms[0] ) ) ? $type_terms[0]->name : '',
			'heroImage'         => (string) ( get_the_post_thumbnail_url( $post, 'large' ) ?: '' ),
			'featuredDishSlugs' => self::map_post_ids_to_slugs( (array) get_post_meta( $post->ID, 'ef_featured_dish_ids', true ) ),
			'stats'             => [
				'postsCount'       => count( CommunityRepository::get_posts_for_celebration( $post->ID ) ),
				'completionsCount' => self::count_completed_celebrations( $post->ID ),
				'averageRating'    => self::average_celebration_rating( $post->ID ),
			],
		];
	}

	/**
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	public static function map_dish( $post ): array {
		$cuisine        = wp_get_post_terms( $post->ID, 'ef_cuisine' );
		$dish_type      = wp_get_post_terms( $post->ID, 'ef_dish_type' );
		$spice          = wp_get_post_terms( $post->ID, 'ef_spice_level' );
		$country_terms  = wp_get_post_terms( $post->ID, 'ef_country' );

		return [
			'id'                => $post->ID,
			'slug'              => $post->post_name,
			'title'             => get_the_title( $post ),
			'description'       => $post->post_excerpt !== '' ? $post->post_excerpt : wp_strip_all_tags( $post->post_content ),
			'originCountry'     => ( function() use ( $post ) {
				$origin_country = (string) get_post_meta( $post->ID, 'ef_origin_country', true );
				$country_terms = wp_get_post_terms( $post->ID, 'ef_country' );
				if ( ! is_wp_error( $country_terms ) && isset( $country_terms[0] ) ) {
					$flag = get_term_meta( $country_terms[0]->term_id, 'ef_flag_emoji', true );
					if ( $flag && ! str_contains( $origin_country, $flag ) ) {
						return $flag . ' ' . $origin_country;
					}
				}
				return $origin_country;
			} )(),
			'countrySlug'       => ( ! is_wp_error( $country_terms ) && isset( $country_terms[0] ) ) ? $country_terms[0]->slug : '',
			'cuisineType'       => ( ! is_wp_error( $cuisine ) && isset( $cuisine[0] ) ) ? $cuisine[0]->name : '',
			'dishType'          => ( ! is_wp_error( $dish_type ) && isset( $dish_type[0] ) ) ? $dish_type[0]->name : '',
			'spiceLevel'        => ( ! is_wp_error( $spice ) && isset( $spice[0] ) ) ? $spice[0]->name : '',
			'culturalMeaning'   => (string) get_post_meta( $post->ID, 'ef_cultural_meaning', true ),
			'averageRating'     => (float) get_post_meta( $post->ID, 'ef_average_rating', true ),
			'ingredients'       => array_values( array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_ingredients', true ) ) ),
			'gallery'           => array_values( array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_gallery_urls', true ) ) ),
			'heroImage'         => (string) ( get_the_post_thumbnail_url( $post, 'large' ) ?: '' ),
			'celebrationSlugs'  => self::map_post_ids_to_slugs( (array) get_post_meta( $post->ID, 'ef_celebration_ids', true ) ),
		];
	}

	/**
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	public static function map_country( $post ): array {
		return [
			'name'             => ( function() use ( $post ) {
				$name = get_the_title( $post );
				$country_term = get_term_by( 'name', $name, 'ef_country' );
				if ( $country_term ) {
					$flag = get_term_meta( $country_term->term_id, 'ef_flag_emoji', true );
					if ( $flag ) {
						return $flag . ' ' . $name;
					}
				}
				return $name;
			} )(),
			'slug'             => $post->post_name,
			'overview'         => (string) get_post_meta( $post->ID, 'ef_overview', true ),
			'heroImage'        => (string) get_post_meta( $post->ID, 'ef_hero_image_url', true ),
			'dishSlugs'        => self::map_post_ids_to_slugs( (array) get_post_meta( $post->ID, 'ef_dish_ids', true ) ),
			'celebrationSlugs' => self::map_post_ids_to_slugs( (array) get_post_meta( $post->ID, 'ef_celebration_ids', true ) ),
		];
	}

	/**
	 * @param list<mixed> $post_ids
	 * @return list<string>
	 */
	/**
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	public static function map_celebration_post( $post ): array {
		$celebration_id = (int) get_post_meta( $post->ID, 'ef_celebration_id', true );
		$dish_id        = (int) get_post_meta( $post->ID, 'ef_dish_id', true );
		$celebration    = $celebration_id > 0 ? get_post( $celebration_id ) : null;
		$dish           = $dish_id > 0 ? get_post( $dish_id ) : null;
		$author         = get_user_by( 'id', (int) $post->post_author );

		return [
			'id'              => $post->ID,
			'userDisplayName' => $author ? (string) $author->display_name : 'Guest',
			'celebrationSlug' => $celebration instanceof \WP_Post ? $celebration->post_name : '',
			'dishSlug'        => $dish instanceof \WP_Post ? $dish->post_name : '',
			'caption'         => (string) get_post_meta( $post->ID, 'ef_caption', true ),
			'rating'          => (float) get_post_meta( $post->ID, 'ef_rating', true ),
			'imageUrl'        => (string) get_post_meta( $post->ID, 'ef_image_url', true ),
			'restaurantName'  => (string) get_post_meta( $post->ID, 'ef_restaurant_name', true ),
			'firstTimeTrying' => (bool) get_post_meta( $post->ID, 'ef_first_time_trying', true ),
			'likesCount'      => (int) get_post_meta( $post->ID, 'ef_likes_count', true ),
			'comments'        => array_map(
				[ self::class, 'map_comment' ],
				CommunityRepository::get_comments_for_post( $post->ID )
			),
		];
	}

	/**
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	public static function map_comment( $post ): array {
		$author = get_user_by( 'id', (int) $post->post_author );

		return [
			'id'        => $post->ID,
			'author'    => $author ? (string) $author->display_name : 'Guest',
			'body'      => (string) $post->post_content,
			'createdAt' => get_post_time( 'c', true, $post ),
		];
	}

	private static function map_post_ids_to_slugs( array $post_ids ): array {
		$slugs = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( $post ) {
				$slugs[] = $post->post_name;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	private static function count_completed_celebrations( int $celebration_id ): int {
		$users = get_users(
			[
				'fields'     => 'ID',
				'number'     => 200,
				'meta_query' => [
					[
						'key'     => 'ef_completed_celebration_ids',
						'value'   => '"' . $celebration_id . '"',
						'compare' => 'LIKE',
					],
				],
			]
		);

		return is_array( $users ) ? count( $users ) : 0;
	}

	private static function average_celebration_rating( int $celebration_id ): float {
		$posts   = CommunityRepository::get_posts_for_celebration( $celebration_id );
		$ratings = [];

		foreach ( $posts as $post ) {
			$rating = (float) get_post_meta( $post->ID, 'ef_rating', true );
			if ( $rating > 0 ) {
				$ratings[] = $rating;
			}
		}

		if ( $ratings === [] ) {
			return 0.0;
		}

		return round( array_sum( $ratings ) / count( $ratings ), 1 );
	}
}
