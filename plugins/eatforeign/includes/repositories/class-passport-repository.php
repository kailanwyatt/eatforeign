<?php
/**
 * Derived passport summaries.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Repositories;

use EatForeign\Support\PassportPhoto;
use EatForeign\Support\PostType;
use WP_Post;
use WP_User;

final class PassportRepository {
	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_public_passports(): array {
		$users = get_users(
			[
				'number'  => 50,
				'orderby' => 'registered',
				'order'   => 'DESC',
			]
		);

		$profiles = [];

		foreach ( $users as $user ) {
			if (! $user instanceof WP_User || ! ModerationRepository::is_profile_public( $user->ID ) ) {
				continue;
			}

			$profile = self::format_user_profile( $user );

			if ( $profile !== null && ( $profile['dishesTried'] > 0 || $profile['celebrationsCompleted'] > 0 ) ) {
				$profiles[] = $profile;
			}
		}

		return $profiles;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_all_passports(): array {
		return self::get_public_passports();
	}

	public static function get_by_slug( string $slug, ?int $viewer_id = null ): ?array {
		$user = get_user_by( 'slug', $slug );

		if (! $user instanceof WP_User ) {
			return null;
		}

		if (! ModerationRepository::can_view_profile( $user->ID, $viewer_id ) ) {
			return null;
		}

		return self::format_user_profile( $user, $viewer_id === $user->ID );
	}

	public static function format_user_profile( ?WP_User $user, bool $include_private_entries = false ): ?array {
		if (! $user instanceof WP_User ) {
			return null;
		}

		$display_name = (string) get_user_meta( $user->ID, 'ef_display_name_override', true );
		if ( $display_name === '' ) {
			$display_name = $user->display_name;
		}

		$entries = self::build_entries( $user->ID, $include_private_entries );

		return [
			'slug'                  => $user->user_nicename,
			'displayName'           => $display_name,
			'homeCity'              => (string) get_user_meta( $user->ID, 'ef_home_city', true ),
			'bio'                   => (string) get_user_meta( $user->ID, 'ef_bio', true ),
			'countriesExplored'     => self::count_countries_explored( $entries ),
			'dishesTried'           => count( $entries ),
			'celebrationsCompleted' => CommunityRepository::count_completed_celebrations_for_user( $user->ID ),
			'entries'               => $entries,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function build_entries( int $user_id, bool $include_private_entries ): array {
		$ratings  = get_user_meta( $user_id, 'ef_dish_ratings', true );
		$ratings  = is_array( $ratings ) ? $ratings : [];
		$activity = get_user_meta( $user_id, 'ef_dish_rating_activity', true );
		$activity = is_array( $activity ) ? $activity : [];
		$entries  = [];

		foreach ( $ratings as $dish_id => $rating ) {
			$dish = get_post( (int) $dish_id );

			if (! $dish instanceof WP_Post ) {
				continue;
			}

			$meta   = is_array( $activity[ $dish_id ] ?? null ) ? $activity[ $dish_id ] : [];
			$note   = self::latest_post_note_for_dish( $user_id, (int) $dish_id, $include_private_entries );
			$post   = self::latest_post_for_dish( $user_id, (int) $dish_id, $include_private_entries );
			$photos = $post instanceof WP_Post ? PassportPhoto::get_for_post( $post->ID ) : [];

			$entries[] = [
				'dishSlug' => $dish->post_name,
				'dishId'   => (int) $dish_id,
				'rating'   => (float) $rating,
				'triedOn'  => (string) ( $meta['triedOn'] ?? current_time( 'Y-m-d' ) ),
				'note'     => $note !== '' ? $note : (string) ( $meta['note'] ?? '' ),
				'photos'   => $photos,
				'imageUrl' => PassportPhoto::first_url( $photos ),
				'postId'   => $post instanceof WP_Post ? $post->ID : 0,
			];
		}

		usort(
			$entries,
			static fn ( array $left, array $right ): int => strcmp( $right['triedOn'], $left['triedOn'] )
		);

		return $entries;
	}

	private static function latest_post_for_dish( int $user_id, int $dish_id, bool $include_private_entries ): ?WP_Post {
		$posts = get_posts(
			[
				'post_type'      => PostType::CELEBRATION_POST,
				'author'         => $user_id,
				'posts_per_page' => 1,
				'post_status'    => $include_private_entries ? [ 'publish', 'pending', 'draft' ] : 'publish',
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

		$post = $posts[0];

		if ( ! $include_private_entries && ! ModerationRepository::is_publicly_visible_post( $post ) ) {
			return null;
		}

		return $post;
	}

	private static function latest_post_note_for_dish( int $user_id, int $dish_id, bool $include_private_entries ): string {
		$post = self::latest_post_for_dish( $user_id, $dish_id, $include_private_entries );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return (string) get_post_meta( $post->ID, 'ef_caption', true );
	}

	/**
	 * @param list<array{dishSlug: string, rating: float, triedOn: string, note: string}> $entries
	 */
	private static function count_countries_explored( array $entries ): int {
		$countries = [];

		foreach ( $entries as $entry ) {
			$dish = get_page_by_path( $entry['dishSlug'], OBJECT, PostType::DISH );

			if (! $dish instanceof WP_Post ) {
				continue;
			}

			$country_slug = (string) get_post_meta( $dish->ID, 'ef_country_slug', true );

			if ( $country_slug !== '' ) {
				$countries[ $country_slug ] = true;
				continue;
			}

			$terms = wp_get_post_terms( $dish->ID, 'ef_country', [ 'fields' => 'slugs' ] );

			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$countries[ (string) $term ] = true;
				}
			}
		}

		return count( $countries );
	}
}
