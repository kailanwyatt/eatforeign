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
	public static function rating_stars( float $rating ): string {
		$filled = max( 0, min( 5, (int) round( $rating ) ) );

		return str_repeat( '★', $filled ) . str_repeat( '☆', 5 - $filled );
	}

	public static function format_passport_date( string $date ): string {
		$date = trim( $date );

		if ( $date === '' ) {
			return '';
		}

		$timestamp = strtotime( $date );

		if ( $timestamp === false ) {
			return '';
		}

		return (string) wp_date( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * @param list<array{url?: string, caption?: string}> $photos
	 * @return list<array{url: string, caption: string}>
	 */
	public static function normalize_passport_photos( array $photos ): array {
		$normalized = [];

		foreach ( $photos as $photo ) {
			if ( ! is_array( $photo ) ) {
				continue;
			}

			$url = isset( $photo['url'] ) ? trim( (string) $photo['url'] ) : '';

			if ( $url === '' ) {
				continue;
			}

			$normalized[] = [
				'url'     => $url,
				'caption' => isset( $photo['caption'] ) ? trim( (string) $photo['caption'] ) : '',
			];
		}

		return $normalized;
	}

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

	/**
	 * Primary nav "Today" — home feed only, not every URL where WP still reports front page.
	 */
	public static function request_path(): string {
		$request_path = '/';

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_path = (string) strtok( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), '?' );
		}

		$request_path = '/' . trim( (string) $request_path, '/' );

		return $request_path === '' ? '/' : $request_path;
	}

	public static function is_today_nav_active(): bool {
		if ( self::current_ef_route() !== '' ) {
			return false;
		}

		if ( is_singular() || is_search() || is_404() ) {
			return false;
		}

		return self::request_path() === '/';
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
