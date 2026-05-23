<?php
/**
 * Google Gemini image generation for photorealistic dish photos.
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class GeminiImageClient {
	private const DEFAULT_MODEL = 'gemini-2.5-flash-image';

	/** @var list<string> */
	private const IMAGE_MODELS = [
		'gemini-2.5-flash-image',
		'gemini-2.0-flash-preview-image-generation',
	];

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function generate_for_dish( int $dish_id ): array|\WP_Error {
		if ( ! current_user_can( 'edit_post', $dish_id ) ) {
			return new \WP_Error( 'eatforeign_unauthorized', 'You cannot edit this dish.' );
		}

		$post = get_post( $dish_id );
		if ( ! $post || $post->post_type !== 'ef_dish' ) {
			return new \WP_Error( 'eatforeign_invalid_dish', 'Invalid dish post.' );
		}

		$api_key = (string) get_option( 'eatforeign_ai_api_key', '' );
		if ( $api_key === '' ) {
			return new \WP_Error(
				'eatforeign_missing_key',
				'Gemini API key is not configured. Add your Google Gemini API key under Settings → EatForeign API.'
			);
		}

		if ( ! self::check_daily_limit() ) {
			return new \WP_Error( 'eatforeign_limit_reached', 'Daily Gemini image generation limit reached.' );
		}

		$prompt = self::build_photorealistic_prompt( $dish_id );
		$model  = self::resolve_model( (string) get_option( 'eatforeign_gemini_image_model', self::DEFAULT_MODEL ) );

		$result = self::generate_with_model( $dish_id, $api_key, $model, $prompt );

		if (
			is_wp_error( $result )
			&& $model !== self::DEFAULT_MODEL
			&& self::is_missing_model_error( $result->get_error_message() )
		) {
			Logger::log( 'GeminiImageClient: Retrying with ' . self::DEFAULT_MODEL . '...' );
			self::persist_model_choice( self::DEFAULT_MODEL );

			return self::generate_with_model( $dish_id, $api_key, self::DEFAULT_MODEL, $prompt );
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function generate_with_model( int $dish_id, string $api_key, string $model, string $prompt ): array|\WP_Error {
		$post = get_post( $dish_id );
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$body = [
			'contents'         => [
				[
					'parts' => [
						[ 'text' => $prompt ],
					],
				],
			],
			'generationConfig' => [
				'responseModalities' => [ 'TEXT', 'IMAGE' ],
			],
		];

		Logger::log(
			"GeminiImageClient: Generating image for dish ID {$dish_id} ({$post->post_title}) using {$model}."
		);

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 120,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$raw_message = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: 'Gemini request failed with status ' . $code;
			Logger::log( 'GeminiImageClient ERROR: ' . $raw_message );

			return new \WP_Error( 'eatforeign_gemini_error', self::format_api_error_message( $raw_message ) );
		}

		$image_url = self::resolve_image_from_response( $data, $dish_id );
		if ( is_wp_error( $image_url ) ) {
			return $image_url;
		}

		self::increment_daily_count();
		DishGeneratedImageStore::save( $dish_id, $image_url );

		Logger::log( "GeminiImageClient: SUCCESS for dish ID {$dish_id}." );

		return [
			'imageUrl' => $image_url,
			'prompt'   => $prompt,
			'provider' => 'gemini',
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function resolve_image_from_response( array $data, int $dish_id ): string|\WP_Error {
		$parts = $data['candidates'][0]['content']['parts'] ?? null;
		if ( ! is_array( $parts ) ) {
			return new \WP_Error( 'eatforeign_gemini_empty', 'Gemini did not return image data.' );
		}

		foreach ( $parts as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}

			$inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
			if ( ! is_array( $inline ) ) {
				continue;
			}

			$b64 = (string) ( $inline['data'] ?? '' );
			if ( $b64 === '' ) {
				continue;
			}

			$mime = (string) ( $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png' );
			$url  = self::persist_base64_image( $b64, $dish_id, $mime );
			if ( ! is_wp_error( $url ) ) {
				return $url;
			}
		}

		return new \WP_Error( 'eatforeign_gemini_empty', 'Gemini did not return an image in the response.' );
	}

	private static function persist_base64_image( string $b64, int $dish_id, string $mime ): string|\WP_Error {
		$binary = base64_decode( $b64, true );

		if ( $binary === false || $binary === '' ) {
			return new \WP_Error( 'eatforeign_gemini_decode', 'Could not decode the generated image.' );
		}

		$extension = str_contains( $mime, 'jpeg' ) || str_contains( $mime, 'jpg' ) ? 'jpg' : 'png';
		$filename  = sanitize_file_name( get_post_field( 'post_name', $dish_id ) ?: 'dish' ) . '-gemini-' . gmdate( 'Ymd-His' ) . '.' . $extension;
		$upload    = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) ) {
			Logger::log( 'GeminiImageClient ERROR: Upload failed - ' . $upload['error'] );

			return new \WP_Error( 'eatforeign_gemini_upload', (string) $upload['error'] );
		}

		return esc_url_raw( (string) $upload['url'] );
	}

	public static function resolve_model( string $configured ): string {
		$configured = trim( $configured );

		if ( in_array( $configured, self::IMAGE_MODELS, true ) ) {
			return $configured;
		}

		if ( $configured !== '' ) {
			Logger::log( "GeminiImageClient: Unknown model '{$configured}'; switching to " . self::DEFAULT_MODEL . '.' );
		}

		self::persist_model_choice( self::DEFAULT_MODEL );

		return self::DEFAULT_MODEL;
	}

	public static function persist_model_choice( string $model ): void {
		update_option( 'eatforeign_gemini_image_model', $model, false );
	}

	private static function is_missing_model_error( string $message ): bool {
		$lower = strtolower( $message );

		return str_contains( $lower, 'not found' )
			|| str_contains( $lower, 'not supported' )
			|| str_contains( $lower, 'invalid model' );
	}

	public static function format_api_error_message( string $raw_message ): string {
		$lower = strtolower( $raw_message );

		if ( str_contains( $lower, 'quota' ) || str_contains( $lower, 'resource_exhausted' ) ) {
			return 'Gemini API quota exceeded. Wait and try again, or raise limits in Google AI Studio. Wikimedia suggested images still work without Gemini.';
		}

		if ( str_contains( $lower, 'rate limit' ) || str_contains( $lower, '429' ) ) {
			return 'Gemini rate limit reached. Wait a few minutes and try again.';
		}

		if ( str_contains( $lower, 'billing' ) || str_contains( $lower, 'permission' ) ) {
			return 'Gemini image generation is not enabled for this API key. Enable an image-capable model in Google AI Studio, then try again.';
		}

		return $raw_message;
	}

	private static function check_daily_limit(): bool {
		$limit = (int) get_option( 'eatforeign_gemini_image_daily_limit', 10 );
		if ( $limit <= 0 ) {
			return true;
		}

		$count_key = 'ef_gemini_image_count_' . gmdate( 'Y_m_d' );
		$current   = (int) get_transient( $count_key );

		return $current < $limit;
	}

	private static function increment_daily_count(): void {
		$count_key = 'ef_gemini_image_count_' . gmdate( 'Y_m_d' );
		$current   = (int) get_transient( $count_key );
		set_transient( $count_key, $current + 1, DAY_IN_SECONDS );
	}

	/**
	 * Gemini Flash image models tend toward stylized output; reinforce photographic realism.
	 */
	public static function build_photorealistic_prompt( int $dish_id ): string {
		$base = OpenAIImageClient::build_prompt( $dish_id );

		return $base . ' CRITICAL STYLE: Output must look like an unedited RAW photograph from a full-frame DSLR with a 50mm lens — real restaurant food styling, natural white balance, soft window light, shallow depth of field, visible texture on food and ceramics, subtle real-world imperfections. NOT illustration, NOT cartoon, NOT 3D render, NOT digital art, NOT oversaturated, NOT HDR, NOT glossy plastic surfaces, NO text, NO watermarks, NO logos, NO people, NO hands.';
	}
}
