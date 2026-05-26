<?php
/**
 * Passport photo list normalization.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class PassportPhoto {
	public const MAX_PER_ENTRY = 6;

	/**
	 * @param mixed $record
	 * @return array{url: string, caption: string}
	 */
	public static function normalize_record( mixed $record ): array {
		if ( ! is_array( $record ) ) {
			return [];
		}

		$url = esc_url_raw( (string) ( $record['url'] ?? '' ) );

		if ( $url === '' ) {
			return [];
		}

		return [
			'url'     => $url,
			'caption' => sanitize_textarea_field( (string) ( $record['caption'] ?? '' ) ),
		];
	}

	/**
	 * @param mixed $list
	 * @return list<array{url: string, caption: string}>
	 */
	public static function normalize_list( mixed $list ): array {
		if ( ! is_array( $list ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $list as $item ) {
			$record = self::normalize_record( $item );

			if ( $record !== [] ) {
				$normalized[] = $record;
			}
		}

		return array_slice( $normalized, 0, self::MAX_PER_ENTRY );
	}

	/**
	 * @param list<array{url: string, caption: string}> $photos
	 * @return list<string>
	 */
	public static function urls_from_list( array $photos ): array {
		return array_values(
			array_filter(
				array_map(
					static fn ( array $photo ): string => (string) ( $photo['url'] ?? '' ),
					$photos
				)
			)
		);
	}

	/**
	 * @param list<array{url: string, caption: string}> $photos
	 */
	public static function first_url( array $photos ): string {
		return (string) ( $photos[0]['url'] ?? '' );
	}

	/**
	 * @return list<array{url: string, caption: string}>
	 */
	public static function get_for_post( int $post_id ): array {
		$photos = self::normalize_list( get_post_meta( $post_id, 'ef_passport_photos', true ) );

		if ( $photos !== [] ) {
			return $photos;
		}

		$url = esc_url_raw( (string) get_post_meta( $post_id, 'ef_image_url', true ) );

		if ( $url === '' ) {
			return [];
		}

		return [
			[
				'url'     => $url,
				'caption' => '',
			],
		];
	}

	/**
	 * @param list<array{url: string, caption: string}> $photos
	 */
	public static function save_for_post( int $post_id, array $photos ): void {
		$photos = self::normalize_list( $photos );
		update_post_meta( $post_id, 'ef_passport_photos', $photos );
		update_post_meta( $post_id, 'ef_gallery_urls', self::urls_from_list( $photos ) );
		update_post_meta( $post_id, 'ef_image_url', self::first_url( $photos ) );
	}
}
