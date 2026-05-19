<?php
/**
 * AI Client for Google Gemini
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class AIClient {
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
	 * @param array<string, mixed> $data
	 */
	private static function record_error_from_payload( array $data ): void {
		self::$last_error_code = 'api_error';
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

	public static function generate_dish_content( string $target_country = '', array $excluded_dishes = [] ): ?array {
		self::clear_last_error();

		$api_key = get_option( 'eatforeign_ai_api_key' );
		if ( empty( $api_key ) ) {
			return null;
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

		if ( empty( $target_country ) ) {
			$prompt = "Discover a traditional cultural dish or food from anywhere in the world that is commonly celebrated. It could be tied to an upcoming holiday, national day, or religious event. Provide detailed, factual information. IMPORTANT: Do NOT include emojis in the `origin_country` field. Provide the official country flag emoji separately in the `country_flag` field.";
		} else {
			$prompt = "Discover a traditional cultural dish or food specifically from {$target_country}. It could be tied to an upcoming holiday, national day, or religious event. Provide detailed, factual information. IMPORTANT: Do NOT include emojis in the `origin_country` field. Provide the official country flag emoji separately in the `country_flag` field.";
		}

		if ( ! empty( $excluded_dishes ) ) {
			$prompt .= "\n\nCRITICAL RULE: You MUST NOT generate any of the following dishes, as they already exist in the database: " . implode( ', ', $excluded_dishes ) . ". Please pick a DIFFERENT dish.";
		}

		$schema = 'You must respond with a JSON object containing the following keys:
- "title": (string) Name of the dish
- "origin_country": (string) The country of origin without any emojis (e.g. "Jamaica")
- "country_flag": (string) The official flag emoji for the country (e.g. "🇯🇲")
- "cuisine": (string) Cuisine type (e.g., "Caribbean", "Korean", "Japanese")
- "dish_type": (string) Dish type (e.g., "Main Course", "Dessert", "Street Food")
- "dietary_type": (string) Primary dietary type (e.g., "Vegan", "Seafood", "Halal", "Meat")
- "cultural_meaning": (string) A rich paragraph about the history and significance of the dish
- "ingredients": (array of strings) Key ingredients
- "recipes": (array of strings) Two or three actual URLs to authentic recipes online
- "spice_level": (string) "Mild", "Medium", or "Hot"
- "celebration_title": (string) The name of a related celebration or holiday. If it is a global holiday (like Christmas or Eid), use the global name. If it is country-specific (like Independence Day), include the country name (e.g., "Jamaican Independence Day").
- "celebration_type": (string) Type of celebration (e.g., "Food Holiday", "Independence Day", "Religious Festival").
- "celebration_date": (string) The date it is typically celebrated (e.g., "YYYY-MM-DD" or "August 6").
- "celebration_short_description": (string) A 1-2 sentence summary of the celebration. MUST be a universal, global description if it is a shared holiday. Do not reference the specific origin_country here unless it is a country-specific holiday.
- "celebration_long_description": (string) A detailed paragraph describing the celebration globally. Any country-specific celebration traditions should be placed in the dish\'s `cultural_meaning`, not here.
- "hashtags": (array of strings) 3-5 popular hashtags for the celebration without the # symbol.
Do not use markdown blocks for the JSON. Just output pure JSON.';

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt . "\n\n" . $schema ],
					]
				]
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature' => 0.7,
			]
		];

		Logger::log( 'AIClient: Starting Gemini API request for content generation...' );

		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			error_log( 'Gemini API Error: ' . $error_msg );
			Logger::log( 'AIClient ERROR: Request failed - ' . $error_msg );
			return null;
		}

		$body_json = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_json, true );

		if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$text = $data['candidates'][0]['content']['parts'][0]['text'];
			$parsed = json_decode( $text, true );
			Logger::log( 'AIClient: Successfully received and parsed JSON for dish: ' . ( $parsed['title'] ?? 'Unknown' ) );
			return $parsed;
		}

		self::record_error_from_payload( is_array( $data ) ? $data : [] );
		$debug_response = print_r( $data, true );
		error_log( 'Gemini API Response Error: ' . $debug_response );
		Logger::log( 'AIClient ERROR: Invalid or empty response from Gemini. Response Payload: ' . $debug_response );
		return null;
	}

	public static function extract_holidays_from_text( string $text ): ?array {
		self::clear_last_error();

		$api_key = get_option( 'eatforeign_ai_api_key' );
		if ( empty( $api_key ) ) {
			return null;
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

		$prompt = "Extract all food holidays, national food days, and related observances from the following text. Return ONLY a JSON array of objects, where each object has a 'title' (e.g. 'National Pizza Day') and a 'date' (e.g. 'February 9'). If the date is a complex rule like 'First Friday in June', just extract the rule as the date string. Do not include anything else in your response. Text:\n\n" . mb_substr($text, 0, 50000);

		$schema = 'You must respond with a JSON array of objects. Example: [{"title": "National Donut Day", "date": "June 7"}]';

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt . "\n\n" . $schema ],
					]
				]
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature' => 0.2,
			]
		];

		Logger::log( 'AIClient: Starting Gemini API request for holiday extraction...' );

		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			Logger::log( 'AIClient ERROR: Extraction Request failed - ' . $error_msg );
			return null;
		}

		$body_json = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_json, true );

		if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_text = $data['candidates'][0]['content']['parts'][0]['text'];
			$parsed = json_decode( $json_text, true );
			Logger::log( 'AIClient: Successfully extracted ' . count((array)$parsed) . ' holidays.' );
			return is_array($parsed) ? $parsed : null;
		}

		self::record_error_from_payload( is_array( $data ) ? $data : [] );
		Logger::log( 'AIClient ERROR: Invalid extraction response from Gemini.' );
		return null;
	}

	public static function generate_content_from_holiday( string $holiday_title, string $holiday_date ): ?array {
		self::clear_last_error();

		$api_key = get_option( 'eatforeign_ai_api_key' );
		if ( empty( $api_key ) ) {
			return null;
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

		$prompt = "Create detailed, factual information for a traditional dish associated with the following upcoming food holiday/observance: '{$holiday_title}' celebrated on '{$holiday_date}'. Provide rich cultural information. IMPORTANT: Do NOT include emojis in the `origin_country` field. Provide the official country flag emoji separately in the `country_flag` field. Make sure the celebration fields accurately reflect '{$holiday_title}' and '{$holiday_date}'.";

		$schema = 'You must respond with a JSON object containing the following keys:
- "title": (string) Name of the dish
- "origin_country": (string) The country of origin without any emojis (e.g. "Jamaica")
- "country_flag": (string) The official flag emoji for the country (e.g. "🇯🇲")
- "cuisine": (string) Cuisine type (e.g., "Caribbean", "Korean", "Japanese")
- "dish_type": (string) Dish type (e.g., "Main Course", "Dessert", "Street Food")
- "dietary_type": (string) Primary dietary type (e.g., "Vegan", "Seafood", "Halal", "Meat")
- "cultural_meaning": (string) A rich paragraph about the history and significance of the dish
- "ingredients": (array of strings) Key ingredients
- "recipes": (array of strings) Two or three actual URLs to authentic recipes online
- "spice_level": (string) "Mild", "Medium", or "Hot"
- "celebration_title": (string) The name of a related celebration or holiday. If it is a global holiday (like Christmas or Eid), use the global name. If it is country-specific (like Independence Day), include the country name (e.g., "Jamaican Independence Day").
- "celebration_type": (string) Type of celebration (e.g., "Food Holiday", "Independence Day", "Religious Festival").
- "celebration_date": (string) The date it is typically celebrated (e.g., "YYYY-MM-DD" or "August 6").
- "celebration_short_description": (string) A 1-2 sentence summary of the celebration. MUST be a universal, global description if it is a shared holiday. Do not reference the specific origin_country here unless it is a country-specific holiday.
- "celebration_long_description": (string) A detailed paragraph describing the celebration globally. Any country-specific celebration traditions should be placed in the dish\'s `cultural_meaning`, not here.
- "hashtags": (array of strings) 3-5 popular hashtags for the celebration without the # symbol.
Do not use markdown blocks for the JSON. Just output pure JSON.';

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt . "\n\n" . $schema ],
					]
				]
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature' => 0.7,
			]
		];

		Logger::log( "AIClient: Starting Gemini API request for specific holiday: {$holiday_title}..." );

		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			Logger::log( 'AIClient ERROR: Request failed - ' . $error_msg );
			return null;
		}

		$body_json = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_json, true );

		if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$text = $data['candidates'][0]['content']['parts'][0]['text'];
			$parsed = json_decode( $text, true );
			Logger::log( 'AIClient: Successfully generated dish for holiday.' );
			return $parsed;
		}

		self::record_error_from_payload( is_array( $data ) ? $data : [] );
		Logger::log( 'AIClient ERROR: Invalid or empty response from Gemini for holiday generation.' );
		return null;
	}

	public static function extract_dishes_from_text( string $text ): ?array {
		self::clear_last_error();

		$api_key = get_option( 'eatforeign_ai_api_key' );
		if ( empty( $api_key ) ) {
			return null;
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

		$prompt = "Extract national dishes from the following text. Return ONLY a JSON array of objects, where each object has a 'title' (e.g. 'Ackee and Saltfish') and a 'country' (e.g. 'Jamaica'). Do not include anything else in your response. Text:\n\n" . mb_substr($text, 0, 50000);

		$schema = 'You must respond with a JSON array of objects. Example: [{"title": "Ackee and Saltfish", "country": "Jamaica"}]';

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt . "\n\n" . $schema ],
					]
				]
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature' => 0.2,
			]
		];

		Logger::log( 'AIClient: Starting Gemini API request for national dish extraction...' );

		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			Logger::log( 'AIClient ERROR: Extraction Request failed - ' . $error_msg );
			return null;
		}

		$body_json = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_json, true );

		if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_text = $data['candidates'][0]['content']['parts'][0]['text'];
			$parsed = json_decode( $json_text, true );
			Logger::log( 'AIClient: Successfully extracted ' . count((array)$parsed) . ' national dishes.' );
			return is_array($parsed) ? $parsed : null;
		}

		self::record_error_from_payload( is_array( $data ) ? $data : [] );
		Logger::log( 'AIClient ERROR: Invalid extraction response from Gemini.' );
		return null;
	}

	public static function generate_content_from_national_dish( string $dish_title, string $country ): ?array {
		self::clear_last_error();

		$api_key = get_option( 'eatforeign_ai_api_key' );
		if ( empty( $api_key ) ) {
			return null;
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

		$prompt = "Create detailed, factual information for the specific traditional dish '{$dish_title}' from the country '{$country}'. Provide rich cultural information. IMPORTANT: Do NOT include emojis in the `origin_country` field. Provide the official country flag emoji separately in the `country_flag` field.";

		$schema = 'You must respond with a JSON object containing the following keys:
- "title": (string) Name of the dish
- "origin_country": (string) The country of origin without any emojis (e.g. "Jamaica")
- "country_flag": (string) The official flag emoji for the country (e.g. "🇯🇲")
- "cuisine": (string) Cuisine type (e.g., "Caribbean", "Korean", "Japanese")
- "dish_type": (string) Dish type (e.g., "Main Course", "Dessert", "Street Food")
- "dietary_type": (string) Primary dietary type (e.g., "Vegan", "Seafood", "Halal", "Meat")
- "cultural_meaning": (string) A rich paragraph about the history and significance of the dish
- "ingredients": (array of strings) Key ingredients
- "recipes": (array of strings) Two or three actual URLs to authentic recipes online
- "spice_level": (string) "Mild", "Medium", or "Hot"
- "celebration_title": (string) The name of a related celebration or holiday. If it is a global holiday (like Christmas or Eid), use the global name. If it is country-specific (like Independence Day), include the country name (e.g., "Jamaican Independence Day").
- "celebration_type": (string) Type of celebration (e.g., "Food Holiday", "Independence Day", "Religious Festival").
- "celebration_date": (string) The exact calendar date the celebration occurs. This field is CRITICAL. You MUST look up the actual date and format it STRICTLY as "YYYY-MM-DD" using the current year (e.g. "2026-12-09" for Tanzanian Independence Day). Do not leave this blank.
- "celebration_short_description": (string) A 1-2 sentence summary of the celebration. MUST be a universal, global description if it is a shared holiday. Do not reference the specific origin_country here unless it is a country-specific holiday.
- "celebration_long_description": (string) A detailed paragraph describing the celebration globally. Any country-specific celebration traditions should be placed in the dish\'s `cultural_meaning`, not here.
- "hashtags": (array of strings) 3-5 popular hashtags for the celebration without the # symbol.
Do not use markdown blocks for the JSON. Just output pure JSON.';

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt . "\n\n" . $schema ],
					]
				]
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature' => 0.7,
			]
		];

		Logger::log( "AIClient: Starting Gemini API request for national dish: {$dish_title} from {$country}..." );

		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			Logger::log( 'AIClient ERROR: Request failed - ' . $error_msg );
			return null;
		}

		$body_json = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_json, true );

		if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$text = $data['candidates'][0]['content']['parts'][0]['text'];
			$parsed = json_decode( $text, true );
			Logger::log( 'AIClient: Successfully generated dish for national dish.' );
			return $parsed;
		}

		self::record_error_from_payload( is_array( $data ) ? $data : [] );
		Logger::log( 'AIClient ERROR: Invalid or empty response from Gemini for national dish generation.' );
		return null;
	}
}
