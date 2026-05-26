<?php
/**
 * Track dishes missing a featured image for editorial follow-up.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class DishImageStatus {
	public const META_KEY = 'ef_needs_featured_image';

	public static function boot(): void {
		add_action( 'transition_post_status', [ self::class, 'on_transition_post_status' ], 10, 3 );
		add_action( 'set_post_thumbnail', [ self::class, 'on_thumbnail_changed' ], 10, 2 );
		add_action( 'delete_post_thumbnail', [ self::class, 'on_thumbnail_deleted' ], 10, 1 );
	}

	public static function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $post->post_type !== PostType::DISH || $new_status !== 'publish' ) {
			return;
		}

		self::sync_for_post( (int) $post->ID );
	}

	public static function on_thumbnail_changed( int $post_id, int $thumbnail_id ): void {
		unset( $thumbnail_id );

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== PostType::DISH ) {
			return;
		}

		self::sync_for_post( $post_id );
	}

	public static function on_thumbnail_deleted( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== PostType::DISH ) {
			return;
		}

		self::sync_for_post( $post_id );
	}

	public static function sync_for_post( int $dish_id ): void {
		if ( $dish_id <= 0 ) {
			return;
		}

		if ( has_post_thumbnail( $dish_id ) ) {
			delete_post_meta( $dish_id, self::META_KEY );
			return;
		}

		update_post_meta( $dish_id, self::META_KEY, '1' );
	}

	public static function has_needs_image_flag( int $dish_id ): bool {
		return get_post_meta( $dish_id, self::META_KEY, true ) === '1';
	}

	public static function has_suggested_sources( int $dish_id ): bool {
		$sources = get_post_meta( $dish_id, 'ef_suggested_image_sources', true );
		if ( is_array( $sources ) && $sources !== [] ) {
			return true;
		}

		$legacy = get_post_meta( $dish_id, 'ef_suggested_images', true );

		return is_array( $legacy ) && $legacy !== [];
	}

	/**
	 * @return list<int>
	 */
	public static function get_draft_dish_ids(): array {
		$query = new \WP_Query(
			[
				'post_type'      => PostType::DISH,
				'post_status'    => 'draft',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		return array_map( 'intval', $query->posts );
	}

	public static function count_draft_dishes(): int {
		return (int) wp_count_posts( PostType::DISH )->draft;
	}

	public static function count_draft_dishes_without_thumbnail(): int {
		$count = 0;

		foreach ( self::get_draft_dish_ids() as $dish_id ) {
			if ( ! has_post_thumbnail( $dish_id ) ) {
				++$count;
			}
		}

		return $count;
	}

	public static function count_needs_image(): int {
		$query = new \WP_Query(
			[
				'post_type'      => PostType::DISH,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => self::META_KEY,
						'value' => '1',
					],
				],
			]
		);

		return (int) $query->found_posts;
	}

	/**
	 * Backfill flags for all published dishes (idempotent).
	 *
	 * @return array{scanned: int, flagged: int, cleared: int}
	 */
	public static function scan_all_published_dishes(): array {
		$query = new \WP_Query(
			[
				'post_type'      => PostType::DISH,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$scanned = 0;
		$flagged = 0;
		$cleared = 0;

		foreach ( $query->posts as $dish_id ) {
			$dish_id = (int) $dish_id;
			++$scanned;
			$had_flag = self::has_needs_image_flag( $dish_id );
			self::sync_for_post( $dish_id );

			if ( self::has_needs_image_flag( $dish_id ) ) {
				if ( ! $had_flag ) {
					++$flagged;
				}
			} elseif ( $had_flag ) {
				++$cleared;
			}
		}

		return [
			'scanned' => $scanned,
			'flagged' => $flagged,
			'cleared' => $cleared,
		];
	}

	/**
	 * Publish every draft dish and return summary counts.
	 *
	 * @return array{published: int, needs_image: int}
	 */
	public static function publish_all_draft_dishes(): array {
		$published   = 0;
		$needs_image = 0;

		foreach ( self::get_draft_dish_ids() as $dish_id ) {
			wp_publish_post( $dish_id );
			++$published;

			if ( self::has_needs_image_flag( $dish_id ) ) {
				++$needs_image;
			}
		}

		return [
			'published'   => $published,
			'needs_image' => $needs_image,
		];
	}

	public static function needs_image_filter_url(): string {
		return add_query_arg(
			[
				'post_type'       => PostType::DISH,
				'ef_needs_image'  => '1',
			],
			admin_url( 'edit.php' )
		);
	}
}
