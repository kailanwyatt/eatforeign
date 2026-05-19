<?php
/**
 * Places Client for Google Places API
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class PlacesClient {
	public static function get_restaurants( string $dish, string $location ): array {
		$api_key = get_option( 'eatforeign_google_places_api_key' );
		if ( empty( $api_key ) ) {
			return [];
		}

		$query = $dish . ' restaurant near ' . $location;
		$cache_key = 'ef_api_places_' . md5( strtolower( $query ) );
		
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			Logger::log( "PlacesClient: CACHE HIT for query '{$query}'." );
			return $cached;
		}

		// Check daily limit
		$limit = (int) get_option( 'eatforeign_places_daily_limit', 20 );
		$today = gmdate( 'Y_m_d' );
		$count_key = 'ef_places_count_' . $today;
		$current_count = (int) get_transient( $count_key );

		if ( $limit > 0 && $current_count >= $limit ) {
			Logger::log( "PlacesClient BLOCKED: Daily limit reached ({$limit}). Cannot search '{$query}'." );
			return [];
		}

		Logger::log( "PlacesClient: LIVE LOOKUP for query '{$query}'..." );

		// Using Google Places Text Search (New)
		$url = 'https://places.googleapis.com/v1/places:searchText';
		
		$body = [
			'textQuery' => $query,
			'maxResultCount' => 10,
		];

		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json',
				'X-Goog-Api-Key' => $api_key,
				'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.websiteUri,places.regularOpeningHours,places.rating'
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			error_log( 'Places API Error: ' . $error_msg );
			Logger::log( "PlacesClient ERROR: Request failed - {$error_msg}" );
			return [];
		}

		$body_json = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_json, true );

		$restaurants = [];
		if ( ! empty( $data['places'] ) ) {
			foreach ( $data['places'] as $place ) {
				$restaurants[] = [
					'id' => $place['id'] ?? '',
					'name' => $place['displayName']['text'] ?? '',
					'address' => $place['formattedAddress'] ?? '',
					'lat' => $place['location']['latitude'] ?? 0,
					'lng' => $place['location']['longitude'] ?? 0,
					'website' => $place['websiteUri'] ?? '',
					'rating' => $place['rating'] ?? 0,
				];
			}
		}

		$found_count = count( $restaurants );
		Logger::log( "PlacesClient: SUCCESS. Found {$found_count} restaurants for '{$query}'." );

		// Increment daily counter
		set_transient( $count_key, $current_count + 1, DAY_IN_SECONDS );

		// Cache for 7 days to save credits
		set_transient( $cache_key, $restaurants, 7 * DAY_IN_SECONDS );

		return $restaurants;
	}
}
