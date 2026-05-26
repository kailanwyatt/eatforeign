<?php
/**
 * Dish passport REST routes.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\REST;

use EatForeign\Repositories\CommunityRepository;
use EatForeign\Support\PassportPhoto;
use EatForeign\Support\PostType;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

final class PassportController {
	public static function register_routes(): void {
		register_rest_route(
			'eatforeign/v1',
			'/dishes/(?P<id>\d+)/passport-entry',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ self::class, 'get_entry' ],
					'permission_callback' => [ AccountController::class, 'require_user' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ self::class, 'upsert_entry' ],
					'permission_callback' => [ AccountController::class, 'require_user' ],
				],
			]
		);

		register_rest_route(
			'eatforeign/v1',
			'/dishes/(?P<id>\d+)/passport-photos',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'list_community_photos' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function get_entry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dish_id = absint( $request['id'] );
		$entry   = CommunityRepository::get_user_passport_entry_for_dish( get_current_user_id(), $dish_id );

		if ( $entry === null ) {
			return new WP_REST_Response( [ 'entry' => null ] );
		}

		return new WP_REST_Response( [ 'entry' => $entry ] );
	}

	public static function list_community_photos( WP_REST_Request $request ): WP_REST_Response {
		$dish_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		$photos = CommunityRepository::get_passport_photos_for_dish(
			$dish_id,
			$user_id > 0 ? $user_id : null,
			true
		);

		return new WP_REST_Response( [ 'photos' => $photos ] );
	}

	public static function upsert_entry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dish_id = absint( $request['id'] );
		$dish    = get_post( $dish_id );

		if ( ! $dish instanceof WP_Post || $dish->post_type !== PostType::DISH ) {
			return new WP_Error( 'eatforeign_not_found', 'Dish not found.', [ 'status' => 404 ] );
		}

		$photos = self::collect_photos_from_request( $request );

		$entry = CommunityRepository::upsert_passport_entry(
			get_current_user_id(),
			[
				'dishId'          => $dish_id,
				'rating'          => (float) $request->get_param( 'rating' ),
				'note'            => (string) $request->get_param( 'note' ),
				'triedOn'         => (string) $request->get_param( 'triedOn' ),
				'restaurantName'  => (string) $request->get_param( 'restaurantName' ),
				'firstTimeTrying' => (bool) $request->get_param( 'firstTimeTrying' ),
				'celebrationId'   => absint( $request->get_param( 'celebrationId' ) ),
				'photos'          => $photos,
			]
		);

		if ( $entry === null ) {
			return new WP_Error( 'eatforeign_upsert_failed', 'Could not save passport entry.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'entry'  => $entry,
				'status' => get_post_status( (int) ( $entry['postId'] ?? 0 ) ),
			]
		);
	}

	/**
	 * @return list<array{url: string, caption: string}>
	 */
	private static function collect_photos_from_request( WP_REST_Request $request ): array {
		$json_photos = $request->get_param( 'photos' );

		if ( is_array( $json_photos ) && $json_photos !== [] ) {
			return PassportPhoto::normalize_list( $json_photos );
		}

		$existing = $request->get_param( 'existingPhotos' );

		if ( is_string( $existing ) && $existing !== '' ) {
			$decoded = json_decode( $existing, true );
			$existing = is_array( $decoded ) ? $decoded : [];
		}

		if ( is_array( $existing ) ) {
			$photos = PassportPhoto::normalize_list( $existing );
		} else {
			$photos = [];
		}

		$files = $request->get_file_params();

		if ( ! isset( $files['images'] ) ) {
			return $photos;
		}

		$uploaded = self::normalize_file_array( $files['images'] );
		$captions = $request->get_param( 'imageCaptions' );

		if ( ! is_array( $captions ) ) {
			$captions = $request->get_param( 'image_captions' );
		}

		if ( ! is_array( $captions ) && isset( $_POST['imageCaptions'] ) && is_array( $_POST['imageCaptions'] ) ) {
			$captions = array_map( 'strval', $_POST['imageCaptions'] );
		}

		if ( ! is_array( $captions ) ) {
			$captions = [];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		foreach ( $uploaded as $index => $file ) {
			if ( ! is_array( $file ) || (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) {
				continue;
			}

			$attachment_id = media_handle_sideload( $file, 0 );

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$url = (string) wp_get_attachment_url( (int) $attachment_id );

			if ( $url === '' ) {
				continue;
			}

			$photos[] = [
				'url'     => $url,
				'caption' => sanitize_textarea_field( (string) ( $captions[ $index ] ?? '' ) ),
			];
		}

		return PassportPhoto::normalize_list( $photos );
	}

	/**
	 * @param array<string, mixed> $files
	 * @return list<array<string, mixed>>
	 */
	private static function normalize_file_array( array $files ): array {
		if ( isset( $files['name'] ) && is_array( $files['name'] ) ) {
			$normalized = [];

			foreach ( array_keys( $files['name'] ) as $index ) {
				$normalized[] = [
					'name'     => $files['name'][ $index ] ?? '',
					'type'     => $files['type'][ $index ] ?? '',
					'tmp_name' => $files['tmp_name'][ $index ] ?? '',
					'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
					'size'     => $files['size'][ $index ] ?? 0,
				];
			}

			return $normalized;
		}

		return [ $files ];
	}
}
