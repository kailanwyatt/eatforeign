<?php
/**
 * Small presentation helpers.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

final class Helpers {
	public static function initials( string $name ): string {
		$name = trim( $name );

		if ( $name === '' ) {
			return '?';
		}

		$parts = preg_split( '/\s+/u', $name ) ?: [];

		if ( count( $parts ) >= 2 ) {
			return strtoupper( mb_substr( (string) $parts[0], 0, 1 ) . mb_substr( (string) $parts[ count( $parts ) - 1 ], 0, 1 ) );
		}

		return strtoupper( mb_substr( $name, 0, 2 ) );
	}

	/**
	 * @param list<array{dishSlug?: string, rating?: float}> $entries
	 */
	public static function average_rating_from_entries( array $entries ): float {
		$sum = 0.0;
		$n   = 0;

		foreach ( $entries as $row ) {
			$r = isset( $row['rating'] ) ? (float) $row['rating'] : 0.0;

			if ( $r > 0 ) {
				$sum += $r;
				++$n;
			}
		}

		return $n > 0 ? round( $sum / $n, 1 ) : 0.0;
	}

	public static function current_ef_route(): string {
		$page = get_query_var( 'ef_page' );

		return is_string( $page ) ? $page : '';
	}

	public static function header_location_value(): string {
		if ( is_user_logged_in() ) {
			$city = (string) get_user_meta( get_current_user_id(), 'ef_home_city', true );

			if ( $city !== '' ) {
				return $city;
			}
		}

		return isset( $_COOKIE['ef_header_location'] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE['ef_header_location'] ) ) : '';
	}
}
