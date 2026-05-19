<?php
/**
 * Community participation persistence.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Repositories;

use EatForeign\Support\PostType;
use WP_Post;

final class CommunityRepository {
	/**
	 * @return list<WP_Post>
	 */
	public static function get_posts_for_celebration( int $celebration_id, bool $public_only = true ): array {
		$posts = CatalogRepository::query_posts(
			PostType::CELEBRATION_POST,
			[
				'post_status'    => $public_only ? 'publish' : [ 'publish', 'pending', 'draft' ],
				'meta_key'       => 'ef_celebration_id',
				'meta_value'     => $celebration_id,
				'posts_per_page' => 50,
			]
		);

		if (! $public_only ) {
			return $posts;
		}

		return array_values(
			array_filter(
				$posts,
				static fn ( WP_Post $post ): bool => ModerationRepository::is_publicly_visible_post( $post )
			)
		);
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function get_featured_posts( int $limit = 6 ): array {
		$posts = CatalogRepository::query_posts(
			PostType::CELEBRATION_POST,
			[
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		return array_values(
			array_filter(
				$posts,
				static fn ( WP_Post $post ): bool => ModerationRepository::is_publicly_visible_post( $post )
			)
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public static function create_celebration_post( int $user_id, array $input ): int {
		$post_id = wp_insert_post(
			[
				'post_type'    => PostType::CELEBRATION_POST,
				'post_status'  => ModerationRepository::initial_post_status( $user_id ),
				'post_author'  => $user_id,
				'post_title'   => sanitize_text_field( (string) ( $input['caption'] ?? 'Celebration post' ) ),
				'post_content' => sanitize_textarea_field( (string) ( $input['caption'] ?? '' ) ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, 'ef_celebration_id', absint( $input['celebrationId'] ?? 0 ) );
		update_post_meta( $post_id, 'ef_dish_id', absint( $input['dishId'] ?? 0 ) );
		update_post_meta( $post_id, 'ef_caption', sanitize_textarea_field( (string) ( $input['caption'] ?? '' ) ) );
		update_post_meta( $post_id, 'ef_rating', round( (float) ( $input['rating'] ?? 0 ), 1 ) );
		update_post_meta( $post_id, 'ef_image_url', esc_url_raw( (string) ( $input['imageUrl'] ?? '' ) ) );
		update_post_meta( $post_id, 'ef_restaurant_name', sanitize_text_field( (string) ( $input['restaurantName'] ?? '' ) ) );
		update_post_meta( $post_id, 'ef_first_time_trying', (bool) ( $input['firstTimeTrying'] ?? false ) );
		update_post_meta( $post_id, 'ef_likes_count', 0 );
		$visibility = ModerationRepository::initial_post_status( $user_id ) === 'publish'
			? ModerationRepository::STATUS_APPROVED
			: ModerationRepository::initial_visibility();
		update_post_meta( $post_id, 'ef_visibility', $visibility );

		return (int) $post_id;
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function get_comments_for_post( int $post_id, bool $public_only = true ): array {
		$posts = CatalogRepository::query_posts(
			PostType::COMMENT,
			[
				'post_status'    => $public_only ? 'publish' : [ 'publish', 'pending', 'draft' ],
				'meta_key'       => 'ef_post_id',
				'meta_value'     => $post_id,
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);

		if ( ! $public_only ) {
			return $posts;
		}

		return array_values(
			array_filter(
				$posts,
				static fn ( WP_Post $post ): bool => ModerationRepository::is_publicly_visible_post( $post )
			)
		);
	}

	public static function create_comment( int $user_id, int $post_id, string $body ): int {
		$comment_id = wp_insert_post(
			[
				'post_type'    => PostType::COMMENT,
				'post_status'  => ModerationRepository::initial_post_status( $user_id ),
				'post_author'  => $user_id,
				'post_title'   => 'Comment on post ' . $post_id,
				'post_content' => sanitize_textarea_field( $body ),
			],
			true
		);

		if ( is_wp_error( $comment_id ) ) {
			return 0;
		}

		update_post_meta( $comment_id, 'ef_post_id', $post_id );
		$visibility = ModerationRepository::initial_post_status( $user_id ) === 'publish'
			? ModerationRepository::STATUS_APPROVED
			: ModerationRepository::initial_visibility();
		update_post_meta( $comment_id, 'ef_visibility', $visibility );

		return (int) $comment_id;
	}

	public static function toggle_celebration_completed( int $user_id, int $celebration_id ): array {
		$completed = get_user_meta( $user_id, 'ef_completed_celebration_ids', true );
		$completed = is_array( $completed ) ? array_map( 'absint', $completed ) : [];

		if ( in_array( $celebration_id, $completed, true ) ) {
			$completed = array_values( array_diff( $completed, [ $celebration_id ] ) );
			$active    = false;
		} else {
			$completed[] = $celebration_id;
			$active      = true;
		}

		update_user_meta( $user_id, 'ef_completed_celebration_ids', $completed );

		return [
			'celebrationId' => $celebration_id,
			'completed'     => $active,
		];
	}

	public static function rate_dish( int $user_id, int $dish_id, float $rating ): array {
		$ratings             = get_user_meta( $user_id, 'ef_dish_ratings', true );
		$ratings             = is_array( $ratings ) ? $ratings : [];
		$ratings[ $dish_id ] = round( $rating, 1 );
		update_user_meta( $user_id, 'ef_dish_ratings', $ratings );

		$activity = get_user_meta( $user_id, 'ef_dish_rating_activity', true );
		$activity = is_array( $activity ) ? $activity : [];
		$activity[ $dish_id ] = [
			'triedOn' => current_time( 'Y-m-d' ),
			'note'    => '',
		];
		update_user_meta( $user_id, 'ef_dish_rating_activity', $activity );

		self::recalculate_dish_average_rating( $dish_id );

		return [
			'dishId' => $dish_id,
			'rating' => round( $rating, 1 ),
		];
	}

	public static function recalculate_dish_average_rating( int $dish_id ): void {
		$users  = get_users( [ 'fields' => 'ID' ] );
		$values = [];

		foreach ( $users as $user_id ) {
			$ratings = get_user_meta( (int) $user_id, 'ef_dish_ratings', true );

			if (! is_array( $ratings ) || ! isset( $ratings[ $dish_id ] ) ) {
				continue;
			}

			$values[] = (float) $ratings[ $dish_id ];
		}

		$average = $values === [] ? 0.0 : round( array_sum( $values ) / count( $values ), 1 );
		update_post_meta( $dish_id, 'ef_average_rating', $average );
	}

	public static function set_dish_eat_vote( int $user_id, int $dish_id, string $vote ): array {
		$votes             = get_user_meta( $user_id, 'ef_dish_eat_votes', true );
		$votes             = is_array( $votes ) ? $votes : [];
		$votes[ $dish_id ] = $vote === 'yes' ? 'yes' : 'not-yet';
		update_user_meta( $user_id, 'ef_dish_eat_votes', $votes );

		return [
			'dishId' => $dish_id,
			'vote'   => $votes[ $dish_id ],
		];
	}

	public static function toggle_post_like( int $user_id, int $post_id ): array {
		$liked = get_user_meta( $user_id, 'ef_liked_post_ids', true );
		$liked = is_array( $liked ) ? array_map( 'absint', $liked ) : [];

		if ( in_array( $post_id, $liked, true ) ) {
			$liked  = array_values( array_diff( $liked, [ $post_id ] ) );
			$active = false;
		} else {
			$liked[] = $post_id;
			$active  = true;
		}

		update_user_meta( $user_id, 'ef_liked_post_ids', $liked );

		$count = (int) get_post_meta( $post_id, 'ef_likes_count', true );
		$count = max( 0, $count + ( $active ? 1 : -1 ) );
		update_post_meta( $post_id, 'ef_likes_count', $count );

		return [
			'postId'     => $post_id,
			'liked'      => $active,
			'likesCount' => $count,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public static function update_profile( int $user_id, array $input ): array {
		if ( isset( $input['displayName'] ) ) {
			update_user_meta( $user_id, 'ef_display_name_override', sanitize_text_field( (string) $input['displayName'] ) );
		}

		if ( isset( $input['homeCity'] ) ) {
			update_user_meta( $user_id, 'ef_home_city', sanitize_text_field( (string) $input['homeCity'] ) );
		}

		if ( isset( $input['bio'] ) ) {
			update_user_meta( $user_id, 'ef_bio', sanitize_textarea_field( (string) $input['bio'] ) );
		}

		if ( isset( $input['locationLabel'] ) ) {
			update_user_meta( $user_id, 'ef_preferred_location_label', sanitize_text_field( (string) $input['locationLabel'] ) );
		}

		$profile = PassportRepository::format_user_profile( get_user_by( 'id', $user_id ), true );

		return $profile ?? [];
	}
}
