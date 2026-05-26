<?php
/**
 * Community participation persistence.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Repositories;

use EatForeign\Support\PassportPhoto;
use EatForeign\Support\PostType;
use EatForeign\Support\Sanitizer;
use WP_Post;
use WP_User;

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

	/**
	 * Published celebration post IDs the user has marked complete.
	 *
	 * @param bool $persist_prune When true, drop invalid IDs from user meta.
	 * @return list<int>
	 */
	public static function get_completed_celebration_ids( int $user_id, bool $persist_prune = true ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$raw   = get_user_meta( $user_id, 'ef_completed_celebration_ids', true );
		$ids   = Sanitizer::post_ids( $raw );
		$valid = [];

		foreach ( array_values( array_unique( $ids ) ) as $id ) {
			$post = get_post( $id );

			if (
				$post instanceof WP_Post
				&& $post->post_type === PostType::CELEBRATION
				&& $post->post_status === 'publish'
			) {
				$valid[] = $id;
			}
		}

		if ( $persist_prune && $valid !== $ids ) {
			update_user_meta( $user_id, 'ef_completed_celebration_ids', $valid );
		}

		return $valid;
	}

	public static function count_completed_celebrations_for_user( int $user_id ): int {
		return count( self::get_completed_celebration_ids( $user_id ) );
	}

	public static function user_completed_celebration( int $user_id, int $celebration_id ): bool {
		$celebration_id = absint( $celebration_id );

		if ( $celebration_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		return in_array( $celebration_id, self::get_completed_celebration_ids( $user_id, false ), true );
	}

	public static function count_users_who_completed_celebration( int $celebration_id ): int {
		$celebration_id = absint( $celebration_id );

		if ( $celebration_id <= 0 ) {
			return 0;
		}

		$post = get_post( $celebration_id );

		if (
			! $post instanceof WP_Post
			|| $post->post_type !== PostType::CELEBRATION
			|| $post->post_status !== 'publish'
		) {
			return 0;
		}

		$users = get_users(
			[
				'fields'     => 'ID',
				'number'     => 500,
				'meta_query' => [
					[
						'key'     => 'ef_completed_celebration_ids',
						'value'   => ';i:' . $celebration_id . ';',
						'compare' => 'LIKE',
					],
				],
			]
		);

		if ( ! is_array( $users ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $users as $user_id ) {
			if ( self::user_completed_celebration( (int) $user_id, $celebration_id ) ) {
				++$count;
			}
		}

		return $count;
	}

	public static function toggle_celebration_completed( int $user_id, int $celebration_id ): array {
		$celebration_id = absint( $celebration_id );
		$completed      = self::get_completed_celebration_ids( $user_id, false );

		if ( in_array( $celebration_id, $completed, true ) ) {
			$completed = array_values( array_diff( $completed, [ $celebration_id ] ) );
			$active    = false;
		} else {
			$post = get_post( $celebration_id );

			if (
				$post instanceof WP_Post
				&& $post->post_type === PostType::CELEBRATION
				&& $post->post_status === 'publish'
			) {
				$completed[] = $celebration_id;
				$active      = true;
			} else {
				$active = false;
			}
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

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|null
	 */
	public static function upsert_passport_entry( int $user_id, array $input ): ?array {
		$dish_id = absint( $input['dishId'] ?? 0 );
		$dish    = get_post( $dish_id );

		if ( ! $dish instanceof WP_Post || $dish->post_type !== PostType::DISH ) {
			return null;
		}

		$rating = round( (float) ( $input['rating'] ?? 0 ), 1 );

		if ( $rating > 0 ) {
			self::rate_dish( $user_id, $dish_id, $rating );
		}

		$note     = sanitize_textarea_field( (string) ( $input['note'] ?? '' ) );
		$tried_on = Sanitizer::date( (string) ( $input['triedOn'] ?? '' ) );

		$activity = get_user_meta( $user_id, 'ef_dish_rating_activity', true );
		$activity = is_array( $activity ) ? $activity : [];
		$row      = is_array( $activity[ $dish_id ] ?? null ) ? $activity[ $dish_id ] : [];

		if ( $tried_on !== '' ) {
			$row['triedOn'] = $tried_on;
		} elseif ( ! isset( $row['triedOn'] ) || (string) $row['triedOn'] === '' ) {
			$row['triedOn'] = current_time( 'Y-m-d' );
		}

		if ( $note !== '' ) {
			$row['note'] = $note;
		}

		$activity[ $dish_id ] = $row;
		update_user_meta( $user_id, 'ef_dish_rating_activity', $activity );

		$photos = PassportPhoto::normalize_list( $input['photos'] ?? [] );

		$celebration_id = absint( $input['celebrationId'] ?? 0 );

		if ( $celebration_id <= 0 ) {
			$linked = get_post_meta( $dish_id, 'ef_celebration_ids', true );
			$linked = is_array( $linked ) ? array_map( 'absint', $linked ) : [];

			if ( $linked !== [] ) {
				$celebration_id = (int) $linked[0];
			}
		}

		$existing = self::find_user_dish_celebration_post( $user_id, $dish_id, true );
		$is_new   = $existing === null;

		if ( $existing instanceof WP_Post ) {
			$post_id = $existing->ID;
			self::update_celebration_post_meta(
				$post_id,
				[
					'caption'         => $note,
					'rating'          => $rating,
					'photos'          => $photos,
					'restaurantName'  => (string) ( $input['restaurantName'] ?? '' ),
					'firstTimeTrying' => (bool) ( $input['firstTimeTrying'] ?? false ),
				]
			);
		} else {
			$post_id = self::create_passport_celebration_post(
				$user_id,
				$dish_id,
				$celebration_id,
				[
					'caption'         => $note,
					'rating'          => $rating,
					'photos'          => $photos,
					'restaurantName'  => (string) ( $input['restaurantName'] ?? '' ),
					'firstTimeTrying' => (bool) ( $input['firstTimeTrying'] ?? false ),
				]
			);
		}

		if ( $post_id <= 0 ) {
			return null;
		}

		$entry = self::format_passport_entry_for_dish( $user_id, $dish_id, true );

		if ( $entry === null ) {
			return null;
		}

		$entry['postId'] = $post_id;
		$entry['isNew']  = $is_new;

		return $entry;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_user_passport_entry_for_dish( int $user_id, int $dish_id ): ?array {
		if ( $user_id <= 0 || $dish_id <= 0 ) {
			return null;
		}

		return self::format_passport_entry_for_dish( $user_id, $dish_id, true );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_passport_photos_for_dish( int $dish_id, ?int $exclude_user_id = null, bool $public_only = true, int $limit = 24 ): array {
		if ( $dish_id <= 0 ) {
			return [];
		}

		$posts = CatalogRepository::query_posts(
			PostType::CELEBRATION_POST,
			[
				'post_status'    => $public_only ? 'publish' : [ 'publish', 'pending', 'draft' ],
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => [
					[
						'key'   => 'ef_dish_id',
						'value' => $dish_id,
					],
				],
			]
		);

		$rows = [];

		foreach ( $posts as $post ) {
			if ( $public_only && ! ModerationRepository::is_publicly_visible_post( $post ) ) {
				continue;
			}

			$author_id = (int) $post->post_author;

			if ( $exclude_user_id !== null && $exclude_user_id > 0 && $author_id === $exclude_user_id ) {
				continue;
			}

			$photos = PassportPhoto::get_for_post( $post->ID );

			if ( $photos === [] ) {
				continue;
			}

			$author = get_user_by( 'id', $author_id );

			if ( ! $author instanceof WP_User ) {
				continue;
			}

			$display_name = (string) get_user_meta( $author_id, 'ef_display_name_override', true );

			if ( $display_name === '' ) {
				$display_name = $author->display_name;
			}

			$activity = get_user_meta( $author_id, 'ef_dish_rating_activity', true );
			$activity = is_array( $activity ) ? $activity : [];
			$row      = is_array( $activity[ $dish_id ] ?? null ) ? $activity[ $dish_id ] : [];
			$tried_on = (string) ( $row['triedOn'] ?? '' );
			$ratings  = get_user_meta( $author_id, 'ef_dish_ratings', true );
			$ratings  = is_array( $ratings ) ? $ratings : [];
			$rating   = isset( $ratings[ $dish_id ] ) ? (float) $ratings[ $dish_id ] : (float) get_post_meta( $post->ID, 'ef_rating', true );

			foreach ( $photos as $photo ) {
				$rows[] = [
					'url'               => (string) $photo['url'],
					'caption'           => (string) $photo['caption'],
					'authorDisplayName' => $display_name,
					'authorSlug'        => $author->user_nicename,
					'authorPassportUrl' => home_url( '/passport/' . $author->user_nicename ),
					'triedOn'           => $tried_on,
					'rating'            => $rating,
					'postId'            => $post->ID,
				];
			}
		}

		return array_slice( $rows, 0, $limit );
	}

	private static function find_user_dish_celebration_post( int $user_id, int $dish_id, bool $include_private ): ?WP_Post {
		$posts = get_posts(
			[
				'post_type'      => PostType::CELEBRATION_POST,
				'author'         => $user_id,
				'posts_per_page' => 1,
				'post_status'    => $include_private ? [ 'publish', 'pending', 'draft' ] : 'publish',
				'meta_query'     => [
					[
						'key'   => 'ef_dish_id',
						'value' => $dish_id,
					],
				],
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		if ( $posts === [] ) {
			return null;
		}

		return $posts[0];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private static function create_passport_celebration_post( int $user_id, int $dish_id, int $celebration_id, array $input ): int {
		$dish = get_post( $dish_id );

		if ( ! $dish instanceof WP_Post ) {
			return 0;
		}

		$caption = sanitize_textarea_field( (string) ( $input['caption'] ?? '' ) );
		$title   = $caption !== '' ? $caption : sprintf( 'Passport: %s', $dish->post_title );

		$post_id = wp_insert_post(
			[
				'post_type'    => PostType::CELEBRATION_POST,
				'post_status'  => ModerationRepository::initial_post_status( $user_id ),
				'post_author'  => $user_id,
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => $caption,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, 'ef_celebration_id', $celebration_id );
		update_post_meta( $post_id, 'ef_dish_id', $dish_id );
		update_post_meta( $post_id, 'ef_likes_count', 0 );
		$visibility = ModerationRepository::initial_post_status( $user_id ) === 'publish'
			? ModerationRepository::STATUS_APPROVED
			: ModerationRepository::initial_visibility();
		update_post_meta( $post_id, 'ef_visibility', $visibility );

		self::update_celebration_post_meta( (int) $post_id, $input );

		return (int) $post_id;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private static function update_celebration_post_meta( int $post_id, array $input ): void {
		$caption = sanitize_textarea_field( (string) ( $input['caption'] ?? '' ) );
		update_post_meta( $post_id, 'ef_caption', $caption );
		update_post_meta( $post_id, 'ef_rating', round( (float) ( $input['rating'] ?? 0 ), 1 ) );
		update_post_meta( $post_id, 'ef_restaurant_name', sanitize_text_field( (string) ( $input['restaurantName'] ?? '' ) ) );
		update_post_meta( $post_id, 'ef_first_time_trying', (bool) ( $input['firstTimeTrying'] ?? false ) );
		PassportPhoto::save_for_post( $post_id, PassportPhoto::normalize_list( $input['photos'] ?? [] ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function format_passport_entry_for_dish( int $user_id, int $dish_id, bool $include_private ): ?array {
		$dish = get_post( $dish_id );

		if ( ! $dish instanceof WP_Post ) {
			return null;
		}

		$ratings = get_user_meta( $user_id, 'ef_dish_ratings', true );
		$ratings = is_array( $ratings ) ? $ratings : [];
		$post    = self::find_user_dish_celebration_post( $user_id, $dish_id, $include_private );

		if ( ! isset( $ratings[ $dish_id ] ) && ! $post instanceof WP_Post ) {
			return null;
		}

		$activity = get_user_meta( $user_id, 'ef_dish_rating_activity', true );
		$activity = is_array( $activity ) ? $activity : [];
		$row    = is_array( $activity[ $dish_id ] ?? null ) ? $activity[ $dish_id ] : [];
		$photos = $post instanceof WP_Post ? PassportPhoto::get_for_post( $post->ID ) : [];
		$note     = $post instanceof WP_Post ? (string) get_post_meta( $post->ID, 'ef_caption', true ) : '';

		if ( $note === '' ) {
			$note = (string) ( $row['note'] ?? '' );
		}

		return [
			'dishId'          => $dish_id,
			'dishSlug'        => $dish->post_name,
			'dishTitle'       => $dish->post_title,
			'rating'          => isset( $ratings[ $dish_id ] ) ? (float) $ratings[ $dish_id ] : (float) ( $post ? get_post_meta( $post->ID, 'ef_rating', true ) : 0 ),
			'triedOn'         => (string) ( $row['triedOn'] ?? current_time( 'Y-m-d' ) ),
			'note'            => $note,
			'photos'          => $photos,
			'imageUrl'        => PassportPhoto::first_url( $photos ),
			'postId'          => $post instanceof WP_Post ? $post->ID : 0,
			'restaurantName'  => $post instanceof WP_Post ? (string) get_post_meta( $post->ID, 'ef_restaurant_name', true ) : '',
			'firstTimeTrying' => $post instanceof WP_Post ? (bool) get_post_meta( $post->ID, 'ef_first_time_trying', true ) : false,
			'celebrationId'   => $post instanceof WP_Post ? (int) get_post_meta( $post->ID, 'ef_celebration_id', true ) : 0,
		];
	}
}
