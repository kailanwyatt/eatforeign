<?php
/**
 * OpenAI featured image generation for blog posts.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class BlogImageClient {
	private const API_URL = 'https://api.openai.com/v1/images/generations';

	private const DEFAULT_MODEL = 'gpt-image-1.5';

	/** @var list<string> */
	private const GPT_MODELS = [
		'gpt-image-1.5',
		'gpt-image-1',
		'gpt-image-1-mini',
	];

	public static function generate_and_attach( int $post_id, string $prompt ): int|\WP_Error {
		$api_key = (string) get_option( 'eatforeign_openai_api_key', '' );
		if ( $api_key === '' ) {
			return new \WP_Error( 'eatforeign_content_missing_key', 'OpenAI API key is not configured in EatForeign API settings.' );
		}

		if ( ! self::check_daily_limit() ) {
			return new \WP_Error( 'eatforeign_content_image_limit', 'Daily blog image generation limit reached.' );
		}

		$model = self::resolve_model( (string) get_option( 'eatforeign_openai_image_model', self::DEFAULT_MODEL ) );
		$size  = trim( (string) get_option( 'eatforeign_openai_image_size', '1024x1024' ) );

		$result = self::request_image( $api_key, $model, $prompt, $size, $post_id );

		if (
			is_wp_error( $result )
			&& $model !== self::DEFAULT_MODEL
			&& self::is_missing_model_error( $result->get_error_message() )
		) {
			Logger::log( 'BlogImageClient: Retrying with ' . self::DEFAULT_MODEL );
			$result = self::request_image( $api_key, self::DEFAULT_MODEL, $prompt, $size, $post_id );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$attachment_id = self::create_attachment( $result, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		self::increment_daily_count();
		Logger::log( "BlogImageClient: Featured image set for post ID {$post_id} (attachment {$attachment_id})." );

		return $attachment_id;
	}

	/**
	 * @return array{url: string, b64: string}|string|\WP_Error
	 */
	private static function request_image(
		string $api_key,
		string $model,
		string $prompt,
		string $size,
		int $post_id
	): array|\WP_Error {
		$request_body = [
			'model'         => self::resolve_model( $model ),
			'prompt'        => $prompt,
			'n'             => 1,
			'size'          => self::normalize_gpt_size( $size ),
			'quality'       => 'high',
			'output_format' => 'png',
		];

		Logger::log( "BlogImageClient: Generating image for post ID {$post_id}." );

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
			$raw = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: 'OpenAI request failed with status ' . $code;
			Logger::log( 'BlogImageClient ERROR: ' . $raw );
			return new \WP_Error( 'eatforeign_content_openai_error', $raw );
		}

		$item = $data['data'][0] ?? null;
		if ( ! is_array( $item ) ) {
			return new \WP_Error( 'eatforeign_content_openai_empty', 'OpenAI did not return image data.' );
		}

		if ( ! empty( $item['url'] ) && is_string( $item['url'] ) ) {
			return [ 'url' => esc_url_raw( $item['url'] ), 'b64' => '' ];
		}

		if ( ! empty( $item['b64_json'] ) && is_string( $item['b64_json'] ) ) {
			return [ 'url' => '', 'b64' => $item['b64_json'] ];
		}

		return new \WP_Error( 'eatforeign_content_openai_empty', 'OpenAI did not return an image URL or base64 payload.' );
	}

	/**
	 * @param array{url: string, b64: string} $image_data
	 */
	private static function create_attachment( array $image_data, int $post_id ): int|\WP_Error {
		$title = get_the_title( $post_id ) ?: 'blog-post';
		$slug  = sanitize_file_name( $title );

		if ( $image_data['b64'] !== '' ) {
			$binary = base64_decode( $image_data['b64'], true );
			if ( $binary === false || $binary === '' ) {
				return new \WP_Error( 'eatforeign_content_decode', 'Could not decode the generated image.' );
			}
			$filename = $slug . '-featured-' . gmdate( 'Ymd-His' ) . '.png';
			$upload   = wp_upload_bits( $filename, null, $binary );
			if ( ! empty( $upload['error'] ) ) {
				return new \WP_Error( 'eatforeign_content_upload', (string) $upload['error'] );
			}
			$file_path = $upload['file'];
			$file_url  = $upload['url'];
			$file_type = 'image/png';
		} else {
			$tmp = download_url( $image_data['url'] );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$file_array = [
				'name'     => $slug . '-featured.png',
				'tmp_name' => $tmp,
			];
			$uploaded = media_handle_sideload( $file_array, $post_id );
			if ( is_wp_error( $uploaded ) ) {
				@unlink( $tmp );
				return $uploaded;
			}
			return (int) $uploaded;
		}

		$attachment = [
			'post_mime_type' => $file_type,
			'post_title'     => $title . ' featured image',
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
	}

	public static function resolve_model( string $configured ): string {
		$configured = trim( $configured );
		if ( in_array( $configured, self::GPT_MODELS, true ) ) {
			return $configured;
		}
		return self::DEFAULT_MODEL;
	}

	private static function normalize_gpt_size( string $size ): string {
		$allowed = [ '1024x1024', '1536x1024', '1024x1536', 'auto' ];
		if ( in_array( $size, $allowed, true ) ) {
			return $size;
		}
		return match ( $size ) {
			'1792x1024' => '1536x1024',
			'1024x1792' => '1024x1536',
			default     => '1024x1024',
		};
	}

	private static function is_missing_model_error( string $message ): bool {
		$lower = strtolower( $message );
		return str_contains( $lower, 'does not exist' )
			|| str_contains( $lower, 'model_not_found' )
			|| str_contains( $lower, 'invalid model' );
	}

	private static function check_daily_limit(): bool {
		$limit = (int) get_option( 'eatforeign_content_image_daily_limit', 3 );
		if ( $limit <= 0 ) {
			return true;
		}
		$count_key = 'ef_content_image_count_' . gmdate( 'Y_m_d' );
		return (int) get_transient( $count_key ) < $limit;
	}

	private static function increment_daily_count(): void {
		$count_key = 'ef_content_image_count_' . gmdate( 'Y_m_d' );
		$current   = (int) get_transient( $count_key );
		set_transient( $count_key, $current + 1, DAY_IN_SECONDS );
	}

	public static function build_fallback_prompt( string $title ): string {
		return "Editorial food photography blog header for article titled \"{$title}\". "
			. 'Appetizing dish or celebration spread on a rustic table, natural window light, shallow depth of field, '
			. 'no text, no watermarks, no logos, no people, studio-quality food photography.';
	}
}
