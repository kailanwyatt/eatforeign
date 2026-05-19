<?php
/**
 * REST API Endpoints
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

final class RestAPI {
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_action( 'wp_ajax_eatforeign_generate_dish_image', [ self::class, 'ajax_generate_dish_image' ] );
	}

	public static function ajax_generate_dish_image(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'eatforeign_generate_dish_image' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			wp_send_json_error( 'Missing dish post ID.' );
		}

		$result = OpenAIImageClient::generate_for_dish( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public static function register_routes(): void {
		register_rest_route( 'eatforeign-api/v1', '/restaurants', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'get_restaurants' ],
			'permission_callback' => '__return_true', // Public endpoint
			'args'                => [
				'dish' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'location' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	public static function get_restaurants( WP_REST_Request $request ): WP_REST_Response {
		$dish = $request->get_param( 'dish' );
		$location = $request->get_param( 'location' );

		$restaurants = PlacesClient::get_restaurants( $dish, $location );

		if ( empty( $restaurants ) ) {
			return new WP_REST_Response( [ 'message' => 'No restaurants found or API key not configured.' ], 404 );
		}

		return new WP_REST_Response( $restaurants, 200 );
	}
}
