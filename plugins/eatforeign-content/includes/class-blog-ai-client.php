<?php
/**
 * Gemini client for SEO blog article generation.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class BlogAiClient {
	private const MODEL_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

	private static ?string $last_error_code = null;

	private static int $retry_after_seconds = 0;

	public static function clear_last_error(): void {
		self::$last_error_code = null;
		self::$retry_after_seconds = 0;
	}

	public static function was_rate_limited(): bool {
		return self::$last_error_code === 'rate_limited';
	}

	public static function get_retry_after_seconds(): int {
		return self::$retry_after_seconds;
	}

	/**
	 * @param array<string, mixed> $topic
	 * @return array<string, mixed>|null
	 */
	public static function generate_article( array $topic ): ?array {
		self::clear_last_error();

		$api_key = (string) get_option( 'eatforeign_ai_api_key', '' );
		if ( $api_key === '' ) {
			Logger::log( 'BlogAiClient ERROR: Gemini API key not configured.' );
			return null;
		}

		$prompt  = self::build_prompt( $topic );
		$schema  = self::json_schema();
		$url     = self::MODEL_URL . '?key=' . $api_key;

		$body = [
			'contents'         => [
				[
					'parts' => [
						[ 'text' => $prompt . "\n\n" . $schema ],
					],
				],
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature'      => 0.7,
			],
		];

		Logger::log( 'BlogAiClient: Starting Gemini request for blog article (' . ( $topic['type'] ?? 'unknown' ) . ').' );

		$response = wp_remote_post(
			$url,
			[
				'body'    => wp_json_encode( $body ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => 90,
			]
		);

		if ( is_wp_error( $response ) ) {
			Logger::log( 'BlogAiClient ERROR: ' . $response->get_error_message() );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$parsed = json_decode( $data['candidates'][0]['content']['parts'][0]['text'], true );
			if ( is_array( $parsed ) && ! empty( $parsed['title'] ) ) {
				Logger::log( 'BlogAiClient: Successfully generated article: ' . ( $parsed['title'] ?? '' ) );
				return $parsed;
			}
		}

		self::record_error_from_payload( is_array( $data ) ? $data : [] );
		Logger::log( 'BlogAiClient ERROR: Invalid Gemini response.' );
		return null;
	}

	/**
	 * @param array<string, mixed> $topic
	 */
	private static function build_prompt( array $topic ): string {
		$site_name = get_bloginfo( 'name' );
		$base      = "You are writing an SEO blog post for {$site_name}, a global food celebration platform. "
			. 'Tone: warm, curious, inclusive. Audience: people who love discovering food holidays and cultural dishes. '
			. 'Write 800-1200 words of original content. Use semantic HTML only (h2, h3, p, ul, li, a) — no markdown code fences. '
			. 'Include one clear call-to-action linking to the site. Never invent fake URLs; only use URLs provided below.';

		$type = (string) ( $topic['type'] ?? 'freeform' );

		if ( $type === 'celebration' && ! empty( $topic['celebration'] ) ) {
			$c = $topic['celebration'];
			$lines = [
				$base,
				'',
				'TOPIC: Write about this food celebration and its cultural significance.',
				'Celebration title: ' . ( $c['title'] ?? '' ),
				'Event date: ' . ( $c['event_date'] ?? '' ),
				'Short description: ' . ( $c['short_description'] ?? '' ),
				'Long description: ' . ( $c['long_description'] ?? '' ),
				'Celebration URL (link in article): ' . ( $c['url'] ?? '' ),
			];
			if ( ! empty( $topic['dish'] ) ) {
				$d = $topic['dish'];
				$lines[] = 'Featured dish: ' . ( $d['title'] ?? '' );
				$lines[] = 'Dish cultural context: ' . ( $d['cultural_meaning'] ?? '' );
				$lines[] = 'Dish URL (link in article): ' . ( $d['url'] ?? '' );
			}
			$lines[] = 'Also link naturally to: ' . home_url( '/calendar' ) . ' and ' . home_url( '/directory' );
			return implode( "\n", $lines );
		}

		if ( $type === 'dish' && ! empty( $topic['dish'] ) ) {
			$d = $topic['dish'];
			return implode(
				"\n",
				[
					$base,
					'',
					'TOPIC: Write about this traditional dish, its history, and how it connects to food culture.',
					'Dish title: ' . ( $d['title'] ?? '' ),
					'Origin country: ' . ( $d['origin_country'] ?? '' ),
					'Cultural meaning: ' . ( $d['cultural_meaning'] ?? '' ),
					'Dish URL (link in article): ' . ( $d['url'] ?? '' ),
					'Also link naturally to: ' . home_url( '/calendar' ) . ', ' . home_url( '/directory' ) . ', and ' . home_url( '/register' ),
				]
			);
		}

		return implode(
			"\n",
			[
				$base,
				'',
				'TOPIC: Pick a timely, interesting food holiday, national dish story, or cultural celebration angle relevant this week.',
				'Include internal links to: ' . home_url( '/calendar' ) . ', ' . home_url( '/directory' ) . ', and ' . home_url( '/register' ),
			]
		);
	}

	private static function json_schema(): string {
		return 'Respond with a JSON object only:
- "title": (string) SEO title, ideally under 60 characters
- "slug": (string) URL-friendly slug
- "excerpt": (string) Post excerpt, max 155 characters
- "meta_description": (string) 150-160 characters for search snippets
- "focus_keyword": (string) Primary SEO keyword phrase
- "content_html": (string) Full article HTML with h2/h3, paragraphs, internal links
- "tags": (array of strings) 3-6 relevant tags without # symbol
- "image_prompt": (string) Photorealistic editorial food photo prompt for AI image generation: appetizing scene, no text, no logos, no people, no watermarks';
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function record_error_from_payload( array $data ): void {
		self::$last_error_code     = 'api_error';
		self::$retry_after_seconds = 0;

		if ( empty( $data['error'] ) || ! is_array( $data['error'] ) ) {
			return;
		}

		$code = (int) ( $data['error']['code'] ?? 0 );
		if ( $code !== 429 ) {
			return;
		}

		self::$last_error_code = 'rate_limited';

		$message = (string) ( $data['error']['message'] ?? '' );
		if ( preg_match( '/retry in ([\d.]+)s/i', $message, $matches ) ) {
			self::$retry_after_seconds = (int) ceil( (float) $matches[1] );
		}

		foreach ( $data['error']['details'] ?? [] as $detail ) {
			if ( ! is_array( $detail ) ) {
				continue;
			}
			if ( ( $detail['@type'] ?? '' ) !== 'type.googleapis.com/google.rpc.RetryInfo' ) {
				continue;
			}
			$retry_delay = (string) ( $detail['retryDelay'] ?? '' );
			if ( preg_match( '/(\d+)/', $retry_delay, $delay_match ) ) {
				self::$retry_after_seconds = max( self::$retry_after_seconds, (int) $delay_match[1] );
			}
		}

		if ( self::$retry_after_seconds < 1 ) {
			self::$retry_after_seconds = 60;
		}
	}
}
