<?php
/**
 * Remove content imported by the retired EatForeign Mock Data plugin.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Admin;

use EatForeign\Support\PostType;
use WP_Post;
use WP_User;

final class MockDataCleanup {
	private const SEED_META_KEY = '_efmd_seed';

	/**
	 * @return array<string, int>
	 */
	public static function get_seed_counts(): array {
		return [
			'countries'         => self::count_seeded_posts( PostType::COUNTRY ),
			'dishes'            => self::count_seeded_posts( PostType::DISH ),
			'celebrations'      => self::count_seeded_posts( PostType::CELEBRATION ),
			'restaurants'       => self::count_seeded_posts( PostType::RESTAURANT ),
			'celebration_posts' => self::count_seeded_posts( PostType::CELEBRATION_POST ),
			'comments'          => self::count_seeded_posts( PostType::COMMENT ),
			'users'             => count( self::get_seeded_users() ),
		];
	}

	public static function has_seeded_content(): bool {
		foreach ( self::get_seed_counts() as $count ) {
			if ( $count > 0 ) {
				return true;
			}
		}

		return false;
	}

	public static function remove(): void {
		$post_types = [
			PostType::COMMENT,
			PostType::CELEBRATION_POST,
			PostType::RESTAURANT,
			PostType::CELEBRATION,
			PostType::DISH,
			PostType::COUNTRY,
		];

		foreach ( $post_types as $post_type ) {
			foreach ( self::get_seeded_posts( $post_type ) as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}

		foreach ( self::get_seeded_users() as $user ) {
			if ( $user->ID === 1 ) {
				continue;
			}

			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user->ID );
		}
	}

	private static function count_seeded_posts( string $post_type ): int {
		return count( self::get_seeded_posts( $post_type ) );
	}

	/**
	 * @return list<WP_Post>
	 */
	private static function get_seeded_posts( string $post_type ): array {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => self::SEED_META_KEY,
				'meta_value'     => '1',
			]
		);

		return is_array( $posts ) ? $posts : [];
	}

	/**
	 * @return list<WP_User>
	 */
	private static function get_seeded_users(): array {
		$users = get_users(
			[
				'meta_key'   => self::SEED_META_KEY,
				'meta_value' => '1',
				'number'     => -1,
			]
		);

		return is_array( $users ) ? $users : [];
	}
}
