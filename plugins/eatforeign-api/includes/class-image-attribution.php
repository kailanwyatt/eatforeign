<?php
/**
 * Image attribution helpers (shared shape with EatForeign plugin).
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class ImageAttribution {
	public const TYPE_WIKIMEDIA = 'wikimedia-commons';
	public const TYPE_AI        = 'ai-generated';

	/**
	 * @return array<string, string>
	 */
	public static function wikimedia_record( string $url, string $author, string $license, string $credit_page_url, string $license_url = '' ): array {
		return [
			'url'           => esc_url_raw( $url ),
			'sourceType'    => self::TYPE_WIKIMEDIA,
			'sourceName'    => 'Wikimedia Commons',
			'author'        => sanitize_text_field( $author ),
			'license'       => sanitize_text_field( $license ),
			'licenseUrl'    => esc_url_raw( $license_url ),
			'creditPageUrl' => esc_url_raw( $credit_page_url ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function ai_record( string $url ): array {
		return [
			'url'        => esc_url_raw( $url ),
			'sourceType' => self::TYPE_AI,
			'license'    => 'AI generated',
		];
	}

	/**
	 * Whether a remote file URL (and optional MIME) is a displayable image.
	 */
	public static function is_image_url( string $url, string $mime = '' ): bool {
		$mime = strtolower( trim( $mime ) );

		if ( $mime !== '' ) {
			return str_starts_with( $mime, 'image/' );
		}

		$blocked = [ 'pdf', 'djvu', 'djv', 'ogg', 'ogv', 'ogm', 'webm', 'mp4', 'mp3', 'wav', 'flac', 'mid', 'midi', 'swf', 'zip' ];
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext     = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( $ext !== '' && in_array( $ext, $blocked, true ) ) {
			return false;
		}

		return ! preg_match( '/\.(pdf|djvu|djv|ogg|ogv|webm|mp4|mp3)(\?|$)/i', $url );
	}
}
