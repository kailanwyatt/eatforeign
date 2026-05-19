<?php
/**
 * EatForeign capabilities and roles.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class Capabilities {
	public const MODERATE_COMMUNITY = 'eatforeign_moderate_community';

	public static function register(): void {
		$roles = [ 'administrator', 'editor' ];

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );

			if ( $role ) {
				$role->add_cap( self::MODERATE_COMMUNITY );
			}
		}
	}

	public static function user_can_moderate( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, self::MODERATE_COMMUNITY );
	}
}
