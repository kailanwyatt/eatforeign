<?php
/**
 * Resolves the next blog topic from catalog or freeform.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

use EatForeign\Repositories\CatalogRepository;
use EatForeign\Support\PostType;
use WP_Post;

final class BlogTopicResolver {
	/**
	 * @return array<string, mixed>
	 */
	public static function resolve_next(): array {
		if ( self::catalog_available() ) {
			$celebration = self::find_celebration_topic();
			if ( $celebration !== null ) {
				return $celebration;
			}

			$dish = self::find_dish_topic();
			if ( $dish !== null ) {
				return $dish;
			}
		}

		return [ 'type' => 'freeform' ];
	}

	public static function catalog_available(): bool {
		return class_exists( CatalogRepository::class );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function find_celebration_topic(): ?array {
		$blogged_celebrations = self::get_blogged_celebration_ids();
		$candidates           = array_merge(
			CatalogRepository::get_today_celebrations(),
			CatalogRepository::get_upcoming_celebrations( 30 )
		);

		foreach ( $candidates as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( in_array( $post->ID, $blogged_celebrations, true ) ) {
				continue;
			}

			$topic = [
				'type'        => 'celebration',
				'celebration' => self::format_celebration( $post ),
				'dish'        => null,
			];

			$dish_ids = get_post_meta( $post->ID, 'ef_featured_dish_ids', true );
			if ( is_array( $dish_ids ) && $dish_ids !== [] ) {
				$dish_id = (int) $dish_ids[0];
				$dish    = get_post( $dish_id );
				if ( $dish instanceof WP_Post && $dish->post_status === 'publish' ) {
					$topic['dish'] = self::format_dish( $dish );
				}
			}

			return $topic;
		}

		return null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function find_dish_topic(): ?array {
		$blogged_dishes = self::get_blogged_dish_ids();

		$query = new \WP_Query(
			[
				'post_type'      => PostType::DISH,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post__not_in'   => $blogged_dishes,
				'fields'         => 'ids',
			]
		);

		foreach ( $query->posts as $dish_id ) {
			$dish = get_post( (int) $dish_id );
			if ( $dish instanceof WP_Post ) {
				return [
					'type' => 'dish',
					'dish' => self::format_dish( $dish ),
				];
			}
		}

		return null;
	}

	/**
	 * @return list<int>
	 */
	public static function get_blogged_celebration_ids(): array {
		return self::get_blogged_ids_for_meta( 'ef_blog_source_celebration_id' );
	}

	/**
	 * @return list<int>
	 */
	public static function get_blogged_dish_ids(): array {
		return self::get_blogged_ids_for_meta( 'ef_blog_source_dish_id' );
	}

	/**
	 * @return list<int>
	 */
	private static function get_blogged_ids_for_meta( string $meta_key ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = 'post' AND p.post_status IN ('draft','publish','pending','future','private')
				AND CAST(pm.meta_value AS UNSIGNED) > 0",
				$meta_key
			)
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values( array_unique( array_map( 'intval', $rows ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_celebration( WP_Post $post ): array {
		return [
			'id'                => $post->ID,
			'title'             => get_the_title( $post ),
			'url'               => get_permalink( $post ),
			'event_date'        => (string) get_post_meta( $post->ID, 'ef_event_date', true ),
			'short_description' => (string) get_post_meta( $post->ID, 'ef_short_description', true ),
			'long_description'  => (string) get_post_meta( $post->ID, 'ef_long_description', true ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_dish( WP_Post $post ): array {
		return [
			'id'               => $post->ID,
			'title'            => get_the_title( $post ),
			'url'              => get_permalink( $post ),
			'origin_country'   => (string) get_post_meta( $post->ID, 'ef_origin_country', true ),
			'cultural_meaning' => (string) get_post_meta( $post->ID, 'ef_cultural_meaning', true ),
		];
	}
}
