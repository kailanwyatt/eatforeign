<?php
/**
 * Derived passport summaries.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Repositories;

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
		$completed = get_user_meta( $user->ID, 'ef_completed_celebration_ids', true );
		$completed = is_array( $completed ) ? $completed : [];

		return [
			'slug'                  => $user->user_nicename,
			'displayName'           => $display_name,
			'homeCity'              => (string) get_user_meta( $user->ID, 'ef_home_city', true ),
			'bio'                   => (string) get_user_meta( $user->ID, 'ef_bio', true ),
			'countriesExplored'     => self::count_countries_explored( $entries ),
			'dishesTried'           => count( $entries ),
			'celebrationsCompleted' => count( $completed ),
			'entries'               => $entries,
		];
	}

	/**
	 * @return list<array{dishSlug: string, rating: float, triedOn: string, note: string}>
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

			$meta = is_array( $activity[ $dish_id ] ?? null ) ? $activity[ $dish_id ] : [];
			$note = self::latest_post_note_for_dish( $user_id, (int) $dish_id, $include_private_entries );

			$entries[] = [
				'dishSlug' => $dish->post_name,
				'rating'   => (float) $rating,
				'triedOn'  => (string) ( $meta['triedOn'] ?? current_time( 'Y-m-d' ) ),
				'note'     => $note !== '' ? $note : (string) ( $meta['note'] ?? '' ),
			];
		}

		usort(
			$entries,
			static fn ( array $left, array $right ): int => strcmp( $right['triedOn'], $left['triedOn'] )
		);

		return $entries;
	}

	private static function latest_post_note_for_dish( int $user_id, int $dish_id, bool $include_private_entries ): string {
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
			return '';
		}

		$post = $posts[0];

		if (! $include_private_entries && ! ModerationRepository::is_publicly_visible_post( $post ) ) {
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
