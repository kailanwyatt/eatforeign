<?php
/**
 * OpenAI Images API client for photorealistic dish photos.
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class OpenAIImageClient {
	private const API_URL = 'https://api.openai.com/v1/images/generations';

	private const DEFAULT_MODEL = 'gpt-image-1.5';

	/** @var list<string> */
	private const GPT_MODELS = [
		'gpt-image-1.5',
		'gpt-image-1',
		'gpt-image-1-mini',
	];

	public static function generate_for_dish( int $dish_id ): array|\WP_Error {
		if ( ! current_user_can( 'edit_post', $dish_id ) ) {
			return new \WP_Error( 'eatforeign_unauthorized', 'You cannot edit this dish.' );
		}

		$post = get_post( $dish_id );
		if ( ! $post || $post->post_type !== 'ef_dish' ) {
			return new \WP_Error( 'eatforeign_invalid_dish', 'Invalid dish post.' );
		}

		$api_key = (string) get_option( 'eatforeign_openai_api_key', '' );
		if ( $api_key === '' ) {
			return new \WP_Error( 'eatforeign_missing_key', 'OpenAI API key is not configured in EatForeign API settings.' );
		}

		if ( ! self::check_daily_limit() ) {
			return new \WP_Error( 'eatforeign_limit_reached', 'Daily OpenAI image generation limit reached.' );
		}

		$prompt = self::build_prompt( $dish_id );
		$model  = self::resolve_model( (string) get_option( 'eatforeign_openai_image_model', self::DEFAULT_MODEL ) );
		$size   = trim( (string) get_option( 'eatforeign_openai_image_size', '1024x1024' ) );

		$result = self::generate_for_dish_with_model( $dish_id, $api_key, $model, $prompt, $size );

		if (
			is_wp_error( $result )
			&& $model !== self::DEFAULT_MODEL
			&& self::is_missing_model_error( $result->get_error_message() )
		) {
			Logger::log( 'OpenAIImageClient: Retrying with ' . self::DEFAULT_MODEL . '...' );
			self::persist_model_choice( self::DEFAULT_MODEL );

			return self::generate_for_dish_with_model( $dish_id, $api_key, self::DEFAULT_MODEL, $prompt, $size );
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function generate_for_dish_with_model(
		int $dish_id,
		string $api_key,
		string $model,
		string $prompt,
		string $size
	): array|\WP_Error {
		$post         = get_post( $dish_id );
		$request_body = self::build_request_body( $model, $prompt, $size );

		Logger::log(
			"OpenAIImageClient: Generating image for dish ID {$dish_id} ({$post->post_title}) using {$model} with body: "
			. wp_json_encode( $request_body )
		);

		$response = wp_remote_post(
			self::API_URL,
			[
				'timeout' => 120,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
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
				: 'OpenAI request failed with status ' . $code;
			$message   = self::format_api_error_message( $raw_message );
			Logger::log( 'OpenAIImageClient ERROR: ' . $raw_message );

			return new \WP_Error( 'eatforeign_openai_error', $message );
		}

		$image_url = self::resolve_image_url( $data, $dish_id );
		if ( is_wp_error( $image_url ) ) {
			return $image_url;
		}

		self::increment_daily_count();

		$existing = get_post_meta( $dish_id, 'ef_ai_generated_images', true );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing[] = esc_url_raw( $image_url );
		update_post_meta( $dish_id, 'ef_ai_generated_images', array_values( array_unique( $existing ) ) );

		Logger::log( "OpenAIImageClient: SUCCESS for dish ID {$dish_id}." );

		return [
			'imageUrl' => $image_url,
			'prompt'   => $prompt,
		];
	}

	public static function resolve_model( string $configured ): string {
		$configured = trim( $configured );

		if ( in_array( $configured, self::GPT_MODELS, true ) ) {
			return $configured;
		}

		if ( self::is_legacy_dalle_model( $configured ) ) {
			Logger::log( "OpenAIImageClient: DALL-E model '{$configured}' is not available; switching to " . self::DEFAULT_MODEL . '.' );
		} elseif ( $configured !== '' ) {
			Logger::log( "OpenAIImageClient: Unknown model '{$configured}'; switching to " . self::DEFAULT_MODEL . '.' );
		}

		self::persist_model_choice( self::DEFAULT_MODEL );

		return self::DEFAULT_MODEL;
	}

	public static function persist_model_choice( string $model ): void {
		update_option( 'eatforeign_openai_image_model', $model, false );
	}

	private static function is_legacy_dalle_model( string $model ): bool {
		return in_array( $model, [ 'dall-e-2', 'dall-e-3' ], true );
	}

	private static function is_missing_model_error( string $message ): bool {
		$lower = strtolower( $message );

		return str_contains( $lower, 'does not exist' )
			|| str_contains( $lower, 'model_not_found' )
			|| str_contains( $lower, 'invalid model' );
	}

	public static function format_api_error_message( string $raw_message ): string {
		$lower = strtolower( $raw_message );

		if (
			str_contains( $lower, 'billing hard limit' )
			|| str_contains( $lower, 'insufficient_quota' )
			|| str_contains( $lower, 'exceeded your current quota' )
		) {
			return 'OpenAI billing limit reached. Add credits or raise your spending limit at https://platform.openai.com/settings/organization/billing — then try again. Wikimedia suggested images on this dish still work without OpenAI.';
		}

		if ( str_contains( $lower, 'rate limit' ) ) {
			return 'OpenAI rate limit reached. Wait a few minutes and try again, or lower the daily generation limit in EatForeign API settings.';
		}

		return $raw_message;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_request_body( string $model, string $prompt, string $size ): array {
		$model = self::resolve_model( $model );

		return [
			'model'          => $model,
			'prompt'         => $prompt,
			'n'              => 1,
			'size'           => self::normalize_gpt_size( $size ),
			'quality'        => 'high',
			'output_format'  => 'png',
		];
	}

	private static function normalize_gpt_size( string $size ): string {
		$allowed = [ '1024x1024', '1536x1024', '1024x1536', 'auto' ];

		if ( in_array( $size, $allowed, true ) ) {
			return $size;
		}

		// Map DALL-E landscape/portrait sizes to GPT equivalents.
		return match ( $size ) {
			'1792x1024' => '1536x1024',
			'1024x1792' => '1024x1536',
			default     => '1024x1024',
		};
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function resolve_image_url( array $data, int $dish_id ): string|\WP_Error {
		$item = $data['data'][0] ?? null;

		if ( ! is_array( $item ) ) {
			return new \WP_Error( 'eatforeign_openai_empty', 'OpenAI did not return image data.' );
		}

		if ( ! empty( $item['url'] ) && is_string( $item['url'] ) ) {
			return esc_url_raw( $item['url'] );
		}

		if ( ! empty( $item['b64_json'] ) && is_string( $item['b64_json'] ) ) {
			return self::persist_base64_image( $item['b64_json'], $dish_id );
		}

		return new \WP_Error( 'eatforeign_openai_empty', 'OpenAI did not return an image URL or base64 payload.' );
	}

	private static function persist_base64_image( string $b64, int $dish_id ): string|\WP_Error {
		$binary = base64_decode( $b64, true );

		if ( $binary === false || $binary === '' ) {
			return new \WP_Error( 'eatforeign_openai_decode', 'Could not decode the generated image.' );
		}

		$filename = sanitize_file_name( get_post_field( 'post_name', $dish_id ) ?: 'dish' ) . '-ai-' . gmdate( 'Ymd-His' ) . '.png';
		$upload   = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) ) {
			Logger::log( 'OpenAIImageClient ERROR: Upload failed - ' . $upload['error'] );
			return new \WP_Error( 'eatforeign_openai_upload', (string) $upload['error'] );
		}

		return esc_url_raw( (string) $upload['url'] );
	}

	public static function build_prompt( int $dish_id ): string {
		$title     = get_the_title( $dish_id );
		$origin    = (string) get_post_meta( $dish_id, 'ef_origin_country', true );
		$cuisine   = self::first_term_name( $dish_id, 'ef_cuisine' );
		$spice     = self::first_term_name( $dish_id, 'ef_spice_level' );
		$dish_type = self::first_term_name( $dish_id, 'ef_dish_type' );

		$parts = array_filter(
			[
				$title !== '' ? "A photorealistic food photography shot of {$title}" : 'A photorealistic food photography shot of a traditional dish',
				$origin !== '' ? "from {$origin}" : '',
				$cuisine !== '' ? "({$cuisine} cuisine)" : '',
				$dish_type !== '' ? "served as {$dish_type}" : '',
				$spice !== '' ? "spice level: {$spice}" : '',
			]
		);

		$subject = implode( ' ', $parts );

		return $subject . '. Single beautifully plated dish on a rustic table, natural window lighting, shallow depth of field, appetizing, no text, no watermarks, no logos, no people, no hands, studio-quality food photography.';
	}

	private static function first_term_name( int $post_id, string $taxonomy ): string {
		$terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
		if ( is_wp_error( $terms ) || $terms === [] ) {
			return '';
		}

		return (string) $terms[0];
	}

	private static function check_daily_limit(): bool {
		$limit = (int) get_option( 'eatforeign_openai_daily_limit', 10 );
		if ( $limit <= 0 ) {
			return true;
		}

		$count_key = 'ef_openai_image_count_' . gmdate( 'Y_m_d' );
		$current   = (int) get_transient( $count_key );

		return $current < $limit;
	}

	private static function increment_daily_count(): void {
		$count_key = 'ef_openai_image_count_' . gmdate( 'Y_m_d' );
		$current   = (int) get_transient( $count_key );
		set_transient( $count_key, $current + 1, DAY_IN_SECONDS );
	}
}
