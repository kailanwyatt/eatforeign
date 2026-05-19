<?php
/**
 * Image Client for Wikimedia Commons
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class ImageClient {
	public static function search_images( string $query ): array {
		Logger::log( "ImageClient: Searching Wikimedia Commons for '{$query}'..." );
		$url = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrsearch=' . urlencode( $query ) . '&gsrlimit=5&prop=imageinfo&iiprop=url';

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
		if ( ! empty( $data['query']['pages'] ) ) {
			foreach ( $data['query']['pages'] as $page ) {
				if ( ! empty( $page['imageinfo'][0]['url'] ) ) {
					$img_url = $page['imageinfo'][0]['url'];
					$img_response = wp_remote_head( $img_url, [ 'timeout' => 5 ] );
					if ( ! is_wp_error( $img_response ) && wp_remote_retrieve_response_code( $img_response ) === 200 ) {
						$images[] = $img_url;
					}
				}
			}
		}

		$count = count( $images );
		Logger::log( "ImageClient: SUCCESS. Found {$count} images." );
		return $images;
	}
}
