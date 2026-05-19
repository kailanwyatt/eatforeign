<?php
/**
 * Register post and user meta.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Meta;

use EatForeign\Support\PostType;
use EatForeign\Support\Sanitizer;

final class Meta {
	public static function register(): void {
		self::register_celebration_meta();
		self::register_dish_meta();
		self::register_country_meta();
		self::register_restaurant_meta();
		self::register_celebration_post_meta();
		self::register_comment_meta();
		self::register_user_meta();
	}

	private static function register_celebration_meta(): void {
		self::register_post_meta( PostType::CELEBRATION, 'ef_event_date', [ Sanitizer::class, 'date' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_recurring_rule', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_short_description', [ Sanitizer::class, 'textarea' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_long_description', [ Sanitizer::class, 'textarea' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_featured_dish_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_featured_restaurant_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_hashtags', [ Sanitizer::class, 'string_list' ], 'array' );
		self::register_post_meta( PostType::CELEBRATION, 'ef_seo_metadata', static fn ( mixed $value ): array => is_array( $value ) ? $value : [], 'object', 'string_map' );
	}

	private static function register_dish_meta(): void {
		self::register_post_meta( PostType::DISH, 'ef_origin_country', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::DISH, 'ef_country_slug', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::DISH, 'ef_cultural_meaning', [ Sanitizer::class, 'textarea' ], 'string' );
		self::register_post_meta( PostType::DISH, 'ef_ingredients', [ Sanitizer::class, 'string_list' ], 'array' );
		self::register_post_meta( PostType::DISH, 'ef_gallery_urls', [ Sanitizer::class, 'string_list' ], 'array' );
		self::register_post_meta( PostType::DISH, 'ef_recipes', [ Sanitizer::class, 'recipes' ], 'array', 'recipe_list' );
		self::register_post_meta( PostType::DISH, 'ef_celebration_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_post_meta( PostType::DISH, 'ef_suggested_images', [ Sanitizer::class, 'string_list' ], 'array' );
		self::register_post_meta( PostType::DISH, 'ef_average_rating', static fn ( mixed $value ): float => round( (float) $value, 1 ), 'number' );
		self::register_post_meta( PostType::DISH, 'ef_eat_yes_count', static fn ( mixed $value ): int => max( 0, (int) $value ), 'integer' );
		self::register_post_meta( PostType::DISH, 'ef_eat_total_count', static fn ( mixed $value ): int => max( 0, (int) $value ), 'integer' );
	}

	private static function register_country_meta(): void {
		self::register_post_meta( PostType::COUNTRY, 'ef_overview', [ Sanitizer::class, 'textarea' ], 'string' );
		self::register_post_meta( PostType::COUNTRY, 'ef_hero_image_url', [ Sanitizer::class, 'url' ], 'string' );
		self::register_post_meta( PostType::COUNTRY, 'ef_dish_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_post_meta( PostType::COUNTRY, 'ef_celebration_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
	}

	private static function register_restaurant_meta(): void {
		self::register_post_meta( PostType::RESTAURANT, 'ef_address', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_city', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_state', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_country', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_latitude', static fn ( mixed $value ): float => (float) $value, 'number' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_longitude', static fn ( mixed $value ): float => (float) $value, 'number' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_website', [ Sanitizer::class, 'url' ], 'string' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_social_links', static fn ( mixed $value ): array => is_array( $value ) ? $value : [], 'object', 'string_map' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_image_url', [ Sanitizer::class, 'url' ], 'string' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_verified', static fn ( mixed $value ): bool => (bool) $value, 'boolean' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_dish_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_post_meta( PostType::RESTAURANT, 'ef_celebration_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
	}

	private static function register_celebration_post_meta(): void {
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_celebration_id', static fn ( mixed $value ): int => absint( $value ), 'integer' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_dish_id', static fn ( mixed $value ): int => absint( $value ), 'integer' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_restaurant_id', static fn ( mixed $value ): int => absint( $value ), 'integer' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_caption', [ Sanitizer::class, 'textarea' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_rating', static fn ( mixed $value ): float => round( (float) $value, 1 ), 'number' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_image_url', [ Sanitizer::class, 'url' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_restaurant_name', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_first_time_trying', static fn ( mixed $value ): bool => (bool) $value, 'boolean' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_visibility', [ Sanitizer::class, 'text' ], 'string' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_likes_count', static fn ( mixed $value ): int => max( 0, (int) $value ), 'integer' );
		self::register_post_meta( PostType::CELEBRATION_POST, 'ef_location_label', [ Sanitizer::class, 'text' ], 'string' );
	}

	private static function register_comment_meta(): void {
		self::register_post_meta( PostType::COMMENT, 'ef_post_id', static fn ( mixed $value ): int => absint( $value ), 'integer' );
	}

	private static function register_user_meta(): void {
		self::register_user_meta_field( 'ef_display_name_override', [ Sanitizer::class, 'text' ], 'string' );
		self::register_user_meta_field( 'ef_home_city', [ Sanitizer::class, 'text' ], 'string' );
		self::register_user_meta_field( 'ef_bio', [ Sanitizer::class, 'textarea' ], 'string' );
		self::register_user_meta_field( 'ef_preferred_location_label', [ Sanitizer::class, 'text' ], 'string' );
		self::register_user_meta_field( 'ef_preferred_lat', static fn ( mixed $value ): float => (float) $value, 'number' );
		self::register_user_meta_field( 'ef_preferred_lng', static fn ( mixed $value ): float => (float) $value, 'number' );
		self::register_user_meta_field( 'ef_completed_celebration_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_user_meta_field( 'ef_liked_post_ids', [ Sanitizer::class, 'post_ids' ], 'array', 'integer_list' );
		self::register_user_meta_field( 'ef_dish_ratings', static fn ( mixed $value ): array => is_array( $value ) ? $value : [], 'object', 'number_map' );
		self::register_user_meta_field( 'ef_dish_eat_votes', static fn ( mixed $value ): array => is_array( $value ) ? $value : [], 'object', 'string_map' );
		self::register_user_meta_field( 'ef_dish_rating_activity', static fn ( mixed $value ): array => is_array( $value ) ? $value : [], 'object', 'activity_map' );
		self::register_user_meta_field( 'ef_profile_public', static fn ( mixed $value ): bool => (bool) $value, 'boolean' );
	}

	/**
	 * @param callable(mixed):mixed $sanitize
	 * @param 'integer_list'|'string_list'|'recipe_list'|'string_map'|'number_map'|'activity_map' $rest_schema
	 */
	private static function register_post_meta(
		string $post_type,
		string $key,
		callable $sanitize,
		string $type,
		string $rest_schema = 'string_list'
	): void {
		$args = [
			'type'              => $type,
			'single'            => true,
			'auth_callback'     => static fn (): bool => current_user_can( 'edit_posts' ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => self::rest_args_for_type( $type, $rest_schema ),
		];

		register_post_meta( $post_type, $key, $args );
	}

	/**
	 * @param callable(mixed):mixed $sanitize
	 * @param 'integer_list'|'string_list'|'recipe_list'|'string_map'|'number_map'|'activity_map' $rest_schema
	 */
	private static function register_user_meta_field(
		string $key,
		callable $sanitize,
		string $type,
		string $rest_schema = 'string_list'
	): void {
		register_meta(
			'user',
			$key,
			[
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => self::rest_args_for_type( $type, $rest_schema ),
				'auth_callback'     => static fn ( bool $allowed, string $meta_key, int $user_id ): bool => current_user_can( 'edit_user', $user_id ),
				'sanitize_callback' => $sanitize,
			]
		);
	}

	/**
	 * @param 'integer_list'|'string_list'|'recipe_list'|'string_map'|'number_map'|'activity_map' $rest_schema
	 * @return true|array{schema: array<string, mixed>}
	 */
	private static function rest_args_for_type( string $type, string $rest_schema ): array|bool {
		if ( $type !== 'array' && $type !== 'object' ) {
			return true;
		}

		return [
			'schema' => self::rest_schema( $rest_schema ),
		];
	}

	/**
	 * @param 'integer_list'|'string_list'|'recipe_list'|'string_map'|'number_map'|'activity_map' $rest_schema
	 * @return array<string, mixed>
	 */
	private static function rest_schema( string $rest_schema ): array {
		return match ( $rest_schema ) {
			'integer_list' => [
				'type'  => 'array',
				'items' => [
					'type' => 'integer',
				],
			],
			'string_list' => [
				'type'  => 'array',
				'items' => [
					'type' => 'string',
				],
			],
			'recipe_list' => [
				'type'  => 'array',
				'items' => [
					'type'                 => 'object',
					'properties'           => [
						'publisher' => [
							'type' => 'string',
						],
						'title'     => [
							'type' => 'string',
						],
						'url'       => [
							'type'   => 'string',
							'format' => 'uri',
						],
					],
					'additionalProperties' => false,
				],
			],
			'string_map' => [
				'type'                 => 'object',
				'additionalProperties' => [
					'type' => 'string',
				],
			],
			'number_map' => [
				'type'                 => 'object',
				'additionalProperties' => [
					'type' => 'number',
				],
			],
			'activity_map' => [
				'type'                 => 'object',
				'additionalProperties' => [
					'type'       => 'object',
					'properties' => [
						'triedOn' => [
							'type' => 'string',
						],
						'note'    => [
							'type' => 'string',
						],
					],
				],
			],
		};
	}
}
