<?php
/**
 * Community content moderation.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Repositories;

use EatForeign\Support\Capabilities;
use EatForeign\Support\PostType;
use WP_Post;

final class ModerationRepository {
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	public static function initial_post_status( int $user_id ): string {
		return Capabilities::user_can_moderate( $user_id ) ? 'publish' : 'pending';
	}

	public static function initial_visibility(): string {
		return self::STATUS_PENDING;
	}

	public static function is_publicly_visible_post( WP_Post $post ): bool {
		if ( $post->post_status !== 'publish' ) {
			return false;
		}

		$visibility = (string) get_post_meta( $post->ID, 'ef_visibility', true );

		return $visibility === '' || $visibility === self::STATUS_APPROVED;
	}

	public static function can_view_post( WP_Post $post, ?int $viewer_id = null ): bool {
		if ( self::is_publicly_visible_post( $post ) ) {
			return true;
		}

		$viewer_id = $viewer_id ?? get_current_user_id();

		if ( $viewer_id <= 0 ) {
			return false;
		}

		if ( (int) $post->post_author === $viewer_id ) {
			return true;
		}

		return Capabilities::user_can_moderate( $viewer_id );
	}

	public static function is_profile_public( int $user_id ): bool {
		$visible = get_user_meta( $user_id, 'ef_profile_public', true );

		if ( $visible === '' ) {
			return true;
		}

		return (bool) $visible;
	}

	public static function can_view_profile( int $user_id, ?int $viewer_id = null ): bool {
		$viewer_id = $viewer_id ?? get_current_user_id();

		if ( $viewer_id === $user_id ) {
			return true;
		}

		if ( Capabilities::user_can_moderate( $viewer_id ) ) {
			return true;
		}

		return self::is_profile_public( $user_id );
	}

	public static function set_post_visibility( int $post_id, string $status ): bool {
		$status = self::normalize_status( $status );
		update_post_meta( $post_id, 'ef_visibility', $status );

		$post_status = $status === self::STATUS_APPROVED ? 'publish' : 'pending';

		if ( $status === self::STATUS_REJECTED ) {
			$post_status = 'draft';
		}

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => $post_status,
			]
		);

		return true;
	}

	public static function set_profile_public( int $user_id, bool $public ): void {
		update_user_meta( $user_id, 'ef_profile_public', $public ? 1 : 0 );
	}

	public static function normalize_status( string $status ): string {
		return match ( $status ) {
			self::STATUS_APPROVED, self::STATUS_REJECTED => $status,
			default => self::STATUS_PENDING,
		};
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function query_community_posts( array $args = [] ): array {
		$query_args = array_merge(
			[
				'post_type'      => PostType::CELEBRATION_POST,
				'post_status'    => [ 'publish', 'pending', 'draft' ],
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			],
			$args
		);

		$query = new \WP_Query( $query_args );

		return array_values( $query->posts );
	}
}
