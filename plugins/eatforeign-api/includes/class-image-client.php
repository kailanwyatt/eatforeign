<?php
/**
 * Image Client for Wikimedia Commons
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class ImageClient {
	/**
	 * Search Wikimedia Commons and return attribution-ready image records.
	 *
	 * @return list<array<string, string>>
	 */
	public static function search_images( string $query ): array {
		Logger::log( "ImageClient: Searching Wikimedia Commons for '{$query}'..." );
		$search_query = trim( $query ) . ' filetype:bitmap|drawing';
		$url          = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search'
			. '&gsrnamespace=6&gsrsearch=' . rawurlencode( $search_query )
			. '&gsrlimit=10&prop=imageinfo&iiprop=url|extmetadata|mime';

		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			error_log( 'Image API Error: ' . $error_msg );
			Logger::log( "ImageClient ERROR: Request failed - {$error_msg}" );
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$images = [];
		if ( empty( $data['query']['pages'] ) || ! is_array( $data['query']['pages'] ) ) {
			Logger::log( 'ImageClient: No images found.' );
			return [];
		}

		foreach ( $data['query']['pages'] as $page ) {
			$imageinfo = $page['imageinfo'][0] ?? null;
			if ( ! is_array( $imageinfo ) || empty( $imageinfo['url'] ) ) {
				continue;
			}

			$img_url    = (string) $imageinfo['url'];
			$mime       = (string) ( $imageinfo['mime'] ?? '' );
			$file_title = (string) ( $page['title'] ?? '' );

			if ( ! self::is_commons_image_file( $img_url, $mime, $file_title ) ) {
				continue;
			}
			$img_response = wp_remote_head( $img_url, [ 'timeout' => 5 ] );
			if ( is_wp_error( $img_response ) || wp_remote_retrieve_response_code( $img_response ) !== 200 ) {
				continue;
			}

			$meta = is_array( $imageinfo['extmetadata'] ?? null ) ? $imageinfo['extmetadata'] : [];
			$author = self::meta_value( $meta, 'Artist' );
			if ( $author === '' ) {
				$author = self::meta_value( $meta, 'Credit' );
			}

			$license = self::meta_value( $meta, 'LicenseShortName' );
			if ( $license === '' ) {
				$license = self::meta_value( $meta, 'UsageTerms' );
			}

			$credit_page = (string) ( $imageinfo['descriptionurl'] ?? '' );
			if ( $credit_page === '' && ! empty( $page['title'] ) ) {
				$credit_page = 'https://commons.wikimedia.org/wiki/' . rawurlencode( str_replace( ' ', '_', (string) $page['title'] ) );
			}

			$images[] = ImageAttribution::wikimedia_record(
				$img_url,
				$author,
				$license,
				$credit_page,
				self::meta_value( $meta, 'LicenseUrl' )
			);

			if ( count( $images ) >= 5 ) {
				break;
			}
		}

		$count = count( $images );
		Logger::log( "ImageClient: SUCCESS. Found {$count} images with attribution metadata." );

		return $images;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private static function is_commons_image_file( string $url, string $mime, string $file_title ): bool {
		if ( ! ImageAttribution::is_image_url( $url, $mime ) ) {
			return false;
		}

		if ( preg_match( '/\.(pdf|djvu|djv|ogg|ogv|webm|mp4|mp3)(\s|$)/i', $file_title ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private static function meta_value( array $meta, string $key ): string {
		if ( empty( $meta[ $key ]['value'] ) ) {
			return '';
		}

		$value = (string) $meta[ $key ]['value'];
		$value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( $value );
	}
}
