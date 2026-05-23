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
}
