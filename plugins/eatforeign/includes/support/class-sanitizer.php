<?php
/**
 * Shared sanitizers.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class Sanitizer {
	public static function text( mixed $value ): string {
		return sanitize_text_field( (string) $value );
	}

	public static function textarea( mixed $value ): string {
		return sanitize_textarea_field( (string) $value );
	}

	public static function url( mixed $value ): string {
		return esc_url_raw( (string) $value );
	}

	public static function date( mixed $value ): string {
		$date = sanitize_text_field( (string) $value );

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) !== 1 ) {
			return '';
		}

		return $date;
	}

	/**
	 * @param mixed $value
	 * @return list<int>
	 */
	public static function post_ids( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		}

		if (! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map( 'absint', $value ),
				static fn ( int $id ): bool => $id > 0
			)
		);
	}

	/**
	 * @param mixed $value
	 * @return list<string>
	 */
	public static function string_list( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n|,/', $value ) ?: [];
		}

		if (! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $item ): string => sanitize_text_field( (string) $item ),
					$value
				)
			)
		);
	}

	/**
	 * @param mixed $value
	 * @return list<array{publisher:string,title:string,url:string}>
	 */
	public static function recipes( mixed $value ): array {
		if (! is_array( $value ) ) {
			return [];
		}

		$recipes = [];

		foreach ( $value as $recipe ) {
			if (! is_array( $recipe ) ) {
				continue;
			}

			$publisher = self::text( $recipe['publisher'] ?? '' );
			$title     = self::text( $recipe['title'] ?? '' );
			$url       = self::url( $recipe['url'] ?? '' );

			if ( $publisher === '' || $title === '' || $url === '' ) {
				continue;
			}

			$recipes[] = [
				'publisher' => $publisher,
				'title'     => $title,
				'url'       => $url,
			];
		}

		return $recipes;
	}
}
