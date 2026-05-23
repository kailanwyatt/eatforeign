<?php
/**
 * Normalize and format image source attribution (remote vs AI-generated).
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class ImageAttribution {
	public const TYPE_WIKIMEDIA = 'wikimedia-commons';
	public const TYPE_AI        = 'ai-generated';
	public const TYPE_REMOTE    = 'remote';
	public const TYPE_MANUAL    = 'manual';

	/** User-facing label when an image was created with AI. */
	public const AI_CAPTION = 'AI generated';

	/**
	 * @param mixed $record
	 * @return array<string, string>
	 */
	public static function normalize_record( mixed $record ): array {
		if ( ! is_array( $record ) ) {
			return [];
		}

		$url = esc_url_raw( (string) ( $record['url'] ?? '' ) );
		if ( $url === '' ) {
			return [];
		}

		$source_type = sanitize_key( (string) ( $record['sourceType'] ?? $record['source_type'] ?? self::TYPE_REMOTE ) );
		if ( ! in_array( $source_type, [ self::TYPE_WIKIMEDIA, self::TYPE_AI, self::TYPE_REMOTE, self::TYPE_MANUAL ], true ) ) {
			$source_type = self::TYPE_REMOTE;
		}

		$normalized = [
			'url'           => $url,
			'sourceType'    => $source_type,
			'sourceName'    => sanitize_text_field( (string) ( $record['sourceName'] ?? $record['source_name'] ?? '' ) ),
			'author'        => sanitize_text_field( (string) ( $record['author'] ?? '' ) ),
			'license'       => sanitize_text_field( (string) ( $record['license'] ?? '' ) ),
			'licenseUrl'    => esc_url_raw( (string) ( $record['licenseUrl'] ?? $record['license_url'] ?? '' ) ),
			'creditPageUrl' => esc_url_raw( (string) ( $record['creditPageUrl'] ?? $record['credit_page_url'] ?? '' ) ),
		];

		if ( $source_type === self::TYPE_AI ) {
			$normalized['sourceName'] = '';
			$normalized['author']     = '';
			$normalized['license']    = self::AI_CAPTION;
		}

		return $normalized;
	}

	/**
	 * @param mixed $list
	 * @return list<array<string, string>>
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

		return $normalized;
	}

	/**
	 * @param list<array<string, string>> $sources
	 * @return list<string>
	 */
	public static function urls_from_sources( array $sources ): array {
		return array_values(
			array_unique(
				array_map(
					static fn( array $source ): string => $source['url'],
					$sources
				)
			)
		);
	}

	/**
	 * @param list<array<string, string>> $sources
	 */
	public static function find_by_url( array $sources, string $url ): ?array {
		$url = esc_url_raw( $url );
		if ( $url === '' ) {
			return null;
		}

		foreach ( $sources as $source ) {
			if ( ( $source['url'] ?? '' ) === $url ) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * @param mixed $map
	 * @return array<string, array<string, string>>
	 */
	public static function normalize_map( mixed $map ): array {
		if ( ! is_array( $map ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $map as $key => $value ) {
			$record = self::normalize_record( is_array( $value ) ? $value : [ 'url' => (string) $key ] );
			if ( $record !== [] ) {
				$normalized[ $record['url'] ] = $record;
			}
		}

		return $normalized;
	}

	public static function ai_generated_record( string $url ): array {
		return self::normalize_record(
			[
				'url'        => $url,
				'sourceType' => self::TYPE_AI,
				'license'    => self::AI_CAPTION,
			]
		);
	}

	public static function is_ai_generated( array $record ): bool {
		return ( $record['sourceType'] ?? '' ) === self::TYPE_AI;
	}

	public static function format_credit_line( array $record ): string {
		return self::display_caption( $record );
	}

	/**
	 * Caption shown under images in admin and on the public site.
	 */
	public static function display_caption( array $record ): string {
		if ( self::is_ai_generated( $record ) ) {
			return self::AI_CAPTION;
		}

		$parts = [];

		if ( ! empty( $record['author'] ) ) {
			$parts[] = $record['author'];
		}

		if ( ! empty( $record['sourceName'] ) && ( $record['sourceName'] ?? '' ) !== ( $record['author'] ?? '' ) ) {
			$parts[] = $record['sourceName'];
		}

		if ( ! empty( $record['license'] ) ) {
			$parts[] = $record['license'];
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Featured / hero image attribution for a dish (post meta, attachment, or AI list).
	 *
	 * @return array<string, string>|null
	 */
	public static function resolve_featured_for_post( int $post_id ): ?array {
		$stored = self::get_featured_attribution( get_post_meta( $post_id, 'ef_featured_image_attribution', true ) );
		if ( $stored !== null ) {
			return $stored;
		}

		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id > 0 ) {
			$attachment = self::normalize_record( get_post_meta( $thumbnail_id, '_ef_image_attribution', true ) );
			if ( $attachment !== [] ) {
				return $attachment;
			}
		}

		$hero_url = (string) ( get_the_post_thumbnail_url( $post_id, 'full' ) ?: '' );
		if ( $hero_url === '' ) {
			$gallery = get_post_meta( $post_id, 'ef_gallery_urls', true );
			if ( is_array( $gallery ) && isset( $gallery[0] ) ) {
				$hero_url = esc_url_raw( (string) $gallery[0] );
			}
		}

		if ( $hero_url === '' ) {
			return null;
		}

		$sources = self::merge_legacy_suggested_urls(
			get_post_meta( $post_id, 'ef_suggested_image_sources', true ),
			get_post_meta( $post_id, 'ef_suggested_images', true )
		);
		$match = self::find_by_url( $sources, $hero_url );
		if ( $match !== null ) {
			return $match;
		}

		$ai_images = get_post_meta( $post_id, 'ef_ai_generated_images', true );
		if ( is_array( $ai_images ) && in_array( $hero_url, $ai_images, true ) ) {
			return self::ai_generated_record( $hero_url );
		}

		return null;
	}

	public static function requires_attribution( array $record ): bool {
		return ( $record['sourceType'] ?? '' ) !== self::TYPE_AI;
	}

	/**
	 * @param mixed $value
	 * @return list<array<string, string>>
	 */
	public static function sanitize_meta_list( mixed $value ): array {
		return self::normalize_list( $value );
	}

	/**
	 * @param mixed $value
	 * @return array<string, string>
	 */
	public static function sanitize_meta_record( mixed $value ): array {
		return self::normalize_record( is_array( $value ) ? $value : [] );
	}

	/**
	 * @param mixed $stored
	 * @return list<array<string, string>>
	 */
	public static function get_suggested_sources( mixed $stored ): array {
		return self::normalize_list( $stored );
	}

	/**
	 * Merge legacy URL-only suggested images with structured attribution records.
	 *
	 * @param mixed $stored_sources
	 * @param mixed $legacy_urls
	 * @return list<array<string, string>>
	 */
	public static function merge_legacy_suggested_urls( mixed $stored_sources, mixed $legacy_urls ): array {
		$sources = self::get_suggested_sources( $stored_sources );
		$known   = array_fill_keys( self::urls_from_sources( $sources ), true );

		if ( ! is_array( $legacy_urls ) ) {
			return $sources;
		}

		foreach ( $legacy_urls as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( $url === '' || isset( $known[ $url ] ) ) {
				continue;
			}

			$source_type = str_contains( $url, 'wikimedia.org' ) || str_contains( $url, 'wikipedia.org' )
				? self::TYPE_WIKIMEDIA
				: self::TYPE_REMOTE;

			$sources[] = self::normalize_record(
				[
					'url'        => $url,
					'sourceType' => $source_type,
					'sourceName' => $source_type === self::TYPE_WIKIMEDIA ? 'Wikimedia Commons' : 'Remote image',
				]
			);
			$known[ $url ] = true;
		}

		return $sources;
	}

	/**
	 * @param mixed $stored
	 */
	public static function get_featured_attribution( mixed $stored ): ?array {
		$record = self::normalize_record( is_array( $stored ) ? $stored : [] );
		return $record !== [] ? $record : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function to_graphql( array $record ): array {
		return [
			'url'            => $record['url'] ?? '',
			'sourceType'     => $record['sourceType'] ?? '',
			'sourceName'     => $record['sourceName'] ?? '',
			'author'         => $record['author'] ?? '',
			'license'        => $record['license'] ?? '',
			'licenseUrl'     => $record['licenseUrl'] ?? '',
			'creditPageUrl'  => $record['creditPageUrl'] ?? '',
			'creditLine'     => self::format_credit_line( $record ),
			'caption'        => self::display_caption( $record ),
			'isAiGenerated'  => self::is_ai_generated( $record ),
		];
	}
}
