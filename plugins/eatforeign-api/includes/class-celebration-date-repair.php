<?php
/**
 * Repair missing or invalid ef_event_date on celebrations.
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class CelebrationDateRepair {
	/**
	 * Normalize a raw date string to YYYY-MM-DD for annual food holidays.
	 * Uses the current WordPress year when the year is omitted.
	 */
	public static function normalize_event_date( string $raw ): string {
		$raw = trim( sanitize_text_field( $raw ) );
		if ( $raw === '' ) {
			return '';
		}

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) === 1 ) {
			return self::validate_ymd( (int) $m[1], (int) $m[2], (int) $m[3] ) ? $raw : '';
		}

		$year = (int) current_time( 'Y' );

		// "May 23", "May 23rd", "23 May", "May 23, 2026"
		if ( preg_match( '/^([A-Za-z]+)\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s*(\d{4}))?$/i', $raw, $m ) === 1 ) {
			$month = self::month_number( $m[1] );
			$day   = (int) $m[2];
			$y     = ! empty( $m[3] ) ? (int) $m[3] : $year;
			if ( $month > 0 ) {
				return self::format_ymd( $y, $month, $day );
			}
		}

		if ( preg_match( '/^(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]+)(?:,?\s*(\d{4}))?$/i', $raw, $m ) === 1 ) {
			$month = self::month_number( $m[2] );
			$day   = (int) $m[1];
			$y     = ! empty( $m[3] ) ? (int) $m[3] : $year;
			if ( $month > 0 ) {
				return self::format_ymd( $y, $month, $day );
			}
		}

		// "5/23", "05-23", "5.23"
		if ( preg_match( '/^(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.](\d{2,4}))?$/', $raw, $m ) === 1 ) {
			$month = (int) $m[1];
			$day   = (int) $m[2];
			$y     = isset( $m[3] ) && $m[3] !== '' ? (int) $m[3] : $year;
			if ( $y < 100 ) {
				$y += 2000;
			}
			return self::format_ymd( $y, $month, $day );
		}

		// "2026-5-23" loose
		if ( preg_match( '/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $raw, $m ) === 1 ) {
			return self::format_ymd( (int) $m[1], (int) $m[2], (int) $m[3] );
		}

		$parsed = strtotime( $raw );
		if ( $parsed !== false ) {
			return self::format_ymd(
				(int) gmdate( 'Y', $parsed ),
				(int) gmdate( 'n', $parsed ),
				(int) gmdate( 'j', $parsed )
			);
		}

		return '';
	}

	public static function is_valid_event_date( string $date ): bool {
		return self::normalize_event_date( $date ) !== '' || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) === 1;
	}

	/**
	 * Normalize a holiday title for fuzzy pending-queue matching.
	 */
	public static function normalize_title_key( string $title ): string {
		$key = strtolower( trim( $title ) );
		$key = preg_replace( '/\b(national|international|world|global)\b/', ' ', $key ) ?? $key;
		$key = preg_replace( '/[^a-z0-9]+/', '', $key ) ?? $key;

		return $key;
	}

	/**
	 * @return array<string, array{date: string, raw: string, pending_id: int, pending_title: string}>
	 */
	public static function build_pending_date_index(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value AS holiday_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'ef_holiday_date'
			WHERE p.post_type = 'ef_pending_item'
			AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
			ORDER BY p.ID DESC"
		);

		$index = [];

		foreach ( $rows as $row ) {
			$raw  = (string) $row->holiday_date;
			$date = self::normalize_event_date( $raw );
			if ( $date === '' ) {
				continue;
			}

			$entry = [
				'date'           => $date,
				'raw'            => $raw,
				'pending_id'     => (int) $row->ID,
				'pending_title'  => (string) $row->post_title,
			];

			$exact = trim( (string) $row->post_title );
			if ( $exact !== '' && ! isset( $index[ 'exact:' . $exact ] ) ) {
				$index[ 'exact:' . $exact ] = $entry;
			}

			$fuzzy = self::normalize_title_key( (string) $row->post_title );
			if ( $fuzzy !== '' && ! isset( $index[ 'fuzzy:' . $fuzzy ] ) ) {
				$index[ 'fuzzy:' . $fuzzy ] = $entry;
			}
		}

		return $index;
	}

	/**
	 * @param array<string, array{date: string, raw: string, pending_id: int, pending_title: string}>|null $index
	 * @return array{date: string, raw: string, pending_id: int, pending_title: string, match: string}|null
	 */
	public static function lookup_pending_date( string $title, ?array $index = null ): ?array {
		$title = trim( $title );
		if ( $title === '' ) {
			return null;
		}

		$index = $index ?? self::build_pending_date_index();

		if ( isset( $index[ 'exact:' . $title ] ) ) {
			return array_merge( $index[ 'exact:' . $title ], [ 'match' => 'exact' ] );
		}

		$fuzzy = self::normalize_title_key( $title );
		if ( $fuzzy !== '' && isset( $index[ 'fuzzy:' . $fuzzy ] ) ) {
			return array_merge( $index[ 'fuzzy:' . $fuzzy ], [ 'match' => 'fuzzy' ] );
		}

		return null;
	}

	/**
	 * Look up a pending queue item (including trash) by exact or fuzzy title.
	 */
	public static function find_pending_holiday_date( string $title ): string {
		$found = self::lookup_pending_date( $title );

		return $found['date'] ?? '';
	}

	/**
	 * @return array{
	 *   scanned: int,
	 *   missing: int,
	 *   repaired: int,
	 *   skipped: int,
	 *   items: list<array{id: int, title: string, status: string, source: string, raw: string, normalized: string}>
	 * }
	 */
	public static function repair_all( bool $dry_run = true, array $statuses = [ 'publish', 'draft' ] ): array {
		$celebrations = get_posts(
			[
				'post_type'      => 'ef_celebration',
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$pending_index = self::build_pending_date_index();

		$report = [
			'scanned'  => count( $celebrations ),
			'missing'  => 0,
			'repaired' => 0,
			'skipped'  => 0,
			'items'    => [],
		];

		foreach ( $celebrations as $post ) {
			$stored = (string) get_post_meta( $post->ID, 'ef_event_date', true );
			$valid  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stored ) === 1
				&& self::validate_ymd(
					(int) substr( $stored, 0, 4 ),
					(int) substr( $stored, 5, 2 ),
					(int) substr( $stored, 8, 2 )
				);

			if ( $valid ) {
				continue;
			}

			++$report['missing'];

			$raw        = $stored;
			$normalized = self::normalize_event_date( $stored );
			$source     = 'existing_meta';

			if ( $normalized === '' ) {
				$pending = self::lookup_pending_date( $post->post_title, $pending_index );
				if ( $pending !== null ) {
					$raw        = $pending['raw'];
					$normalized = $pending['date'];
					$source     = $pending['match'] === 'fuzzy'
						? 'pending_queue_fuzzy'
						: 'pending_queue';
				}
			}

			if ( $normalized === '' ) {
				++$report['skipped'];
				$report['items'][] = [
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'status'     => $post->post_status,
					'source'     => 'none',
					'raw'        => $raw,
					'normalized' => '',
				];
				continue;
			}

			if ( ! $dry_run ) {
				update_post_meta( $post->ID, 'ef_event_date', $normalized );
			}

			++$report['repaired'];
			$report['items'][] = [
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'status'     => $post->post_status,
				'source'     => $source,
				'raw'        => $raw,
				'normalized' => $normalized,
			];
		}

		return $report;
	}

	private static function month_number( string $name ): int {
		$name = strtolower( trim( $name ) );
		$map  = [
			'january'   => 1,
			'february'  => 2,
			'march'     => 3,
			'april'     => 4,
			'may'       => 5,
			'june'      => 6,
			'july'      => 7,
			'august'    => 8,
			'september' => 9,
			'october'   => 10,
			'november'  => 11,
			'december'  => 12,
			'jan'       => 1,
			'feb'       => 2,
			'mar'       => 3,
			'apr'       => 4,
			'jun'       => 6,
			'jul'       => 7,
			'aug'       => 8,
			'sep'       => 9,
			'oct'       => 10,
			'nov'       => 11,
			'dec'       => 12,
		];

		return $map[ $name ] ?? 0;
	}

	private static function format_ymd( int $year, int $month, int $day ): string {
		if ( ! self::validate_ymd( $year, $month, $day ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	private static function validate_ymd( int $year, int $month, int $day ): bool {
		return $year >= 1900 && $year <= 2100 && checkdate( $month, $day, $year );
	}
}
