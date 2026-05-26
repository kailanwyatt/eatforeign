<?php
/**
 * Content Generator
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class ContentGenerator {
	/**
	 * Prefer AI origin_country; if empty, use the first non-empty fallback (e.g. bulk target or pending queue country).
	 *
	 * @param string ...$fallbacks Additional candidates in order.
	 */
	private static function effective_origin_country( string $from_ai, string ...$fallbacks ): string {
		$candidate = sanitize_text_field( $from_ai );
		if ( $candidate !== '' ) {
			return $candidate;
		}
		foreach ( $fallbacks as $fb ) {
			$candidate = sanitize_text_field( $fb );
			if ( $candidate !== '' ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Store canonical country slug on the dish when the ef_country term exists.
	 */
	private static function sync_dish_country_slug_meta( int $dish_id ): void {
		$terms = wp_get_post_terms( $dish_id, 'ef_country', [ 'number' => 1 ] );
		if ( is_wp_error( $terms ) || $terms === [] ) {
			return;
		}
		$term = $terms[0];
		if ( $term instanceof \WP_Term ) {
			update_post_meta( $dish_id, 'ef_country_slug', $term->slug );
		}
	}

	public static function generate_and_draft_post( string $target_country = '' ): bool {
		// Find existing dishes to exclude to prevent wasting API requests
		$excluded_dishes = [];
		$args = [
			'post_type'      => 'ef_dish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
		];
		if ( ! empty( $target_country ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'ef_country',
					'field'    => 'name',
					'terms'    => $target_country,
				],
			];
			$args['posts_per_page'] = 200;
		}
		$existing_dish_ids = get_posts( $args );
		foreach ( $existing_dish_ids as $id ) {
			$excluded_dishes[] = get_the_title( $id );
		}

		$data = AIClient::generate_dish_content( $target_country, $excluded_dishes );
		if ( ! $data ) {
			return false;
		}

		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( empty( $title ) ) {
			Logger::log( 'ContentGenerator ERROR: AI returned empty title. Aborting.' );
			return false;
		}

		// Check if dish already exists (safety net in case AI ignores instructions)
		$existing_dish = get_page_by_title( $title, OBJECT, 'ef_dish' );
		if ( $existing_dish ) {
			Logger::log( "ContentGenerator SKIP: Dish '{$title}' already exists." );
			return false; // Avoid duplicates
		}

		Logger::log( "ContentGenerator: Proceeding to draft new dish '{$title}'..." );

		// Find images
		$images = ImageClient::search_images( $title . ' food' );

		// Create the Draft Dish Post
		$dish_id = wp_insert_post( [
			'post_title'   => $title,
			'post_name'    => sanitize_title( $title ),
			'post_type'    => 'ef_dish',
			'post_status'  => 'draft',
		] );

		if ( is_wp_error( $dish_id ) || ! $dish_id ) {
			Logger::log( "ContentGenerator ERROR: Failed to insert dish post '{$title}'." );
			return false;
		}

		Logger::log( "ContentGenerator: Created Dish draft ID {$dish_id}." );

		$country = self::effective_origin_country( (string) ( $data['origin_country'] ?? '' ), $target_country );

		// Save Meta
		update_post_meta( $dish_id, 'ef_origin_country', $country );
		update_post_meta( $dish_id, 'ef_cultural_meaning', wp_kses_post( $data['cultural_meaning'] ?? '' ) );
		update_post_meta( $dish_id, 'ef_ingredients', array_map( 'sanitize_text_field', $data['ingredients'] ?? [] ) );
		update_post_meta( $dish_id, 'ef_recipes', array_map( 'esc_url_raw', $data['recipes'] ?? [] ) );
		self::save_suggested_image_sources( $dish_id, $images );
		
		// Taxonomy: Spice Level
		$spice = sanitize_text_field( $data['spice_level'] ?? '' );
		if ( $spice ) {
			wp_set_object_terms( $dish_id, $spice, 'ef_spice_level' );
		}

		// Taxonomy: Country
		if ( $country ) {
			$term_ids = wp_set_object_terms( $dish_id, $country, 'ef_country' );
			if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
				$flag = sanitize_text_field( $data['country_flag'] ?? '' );
				if ( $flag ) {
					// Save the flag emoji as termmeta on the ef_country term
					update_term_meta( (int) $term_ids[0], 'ef_flag_emoji', $flag );
				}
			}
			self::sync_dish_country_slug_meta( $dish_id );
		}

		// Taxonomy: Cuisine
		$cuisine = sanitize_text_field( $data['cuisine'] ?? '' );
		if ( $cuisine ) {
			wp_set_object_terms( $dish_id, $cuisine, 'ef_cuisine' );
		}

		// Taxonomy: Dish Type
		$dish_type = sanitize_text_field( $data['dish_type'] ?? '' );
		if ( $dish_type ) {
			wp_set_object_terms( $dish_id, $dish_type, 'ef_dish_type' );
		}

		// Taxonomy: Dietary Type
		$dietary_type = sanitize_text_field( $data['dietary_type'] ?? '' );
		if ( $dietary_type ) {
			wp_set_object_terms( $dish_id, $dietary_type, 'ef_dietary_type' );
		}

		// Process Celebration
		$celebration_title = sanitize_text_field( $data['celebration_title'] ?? '' );
		$celebration_id = 0;

		if ( ! empty( $celebration_title ) ) {
			$existing_celebration = get_page_by_title( $celebration_title, OBJECT, 'ef_celebration' );
			
			if ( $existing_celebration ) {
				$celebration_id = $existing_celebration->ID;
				$existing_dish_ids = get_post_meta( $celebration_id, 'ef_featured_dish_ids', true );
				if ( ! is_array( $existing_dish_ids ) ) {
					$existing_dish_ids = [];
				}
				if ( ! in_array( $dish_id, $existing_dish_ids, true ) ) {
					$existing_dish_ids[] = $dish_id;
					update_post_meta( $celebration_id, 'ef_featured_dish_ids', $existing_dish_ids );
				}
				self::ensure_celebration_event_date(
					$celebration_id,
					(string) ( $data['celebration_date'] ?? '' ),
					$celebration_title
				);
			} else {
				$celebration_id = wp_insert_post( [
					'post_title'   => $celebration_title,
					'post_name'    => sanitize_title( $celebration_title ),
					'post_type'    => 'ef_celebration',
					'post_status'  => 'draft',
				] );

				if ( ! is_wp_error( $celebration_id ) && $celebration_id ) {
					$date = self::resolve_celebration_event_date(
						(string) ( $data['celebration_date'] ?? '' ),
						$celebration_title
					);
					if ( $date !== '' ) {
						update_post_meta( $celebration_id, 'ef_event_date', $date );
					}
					update_post_meta( $celebration_id, 'ef_featured_dish_ids', [ $dish_id ] );
					update_post_meta( $celebration_id, 'ef_short_description', sanitize_text_field( $data['celebration_short_description'] ?? '' ) );
					update_post_meta( $celebration_id, 'ef_long_description', wp_kses_post( $data['celebration_long_description'] ?? '' ) );
					update_post_meta( $celebration_id, 'ef_hashtags', array_map( 'sanitize_text_field', $data['hashtags'] ?? [] ) );

					$celeb_type = sanitize_text_field( $data['celebration_type'] ?? '' );
					if ( $celeb_type ) {
						wp_set_object_terms( $celebration_id, $celeb_type, 'ef_celebration_type' );
					}
				} else {
					$celebration_id = 0;
				}
			}
		}

		if ( $celebration_id ) {
			update_post_meta( $dish_id, 'ef_celebration_ids', [ $celebration_id ] );
		}

		// Process Country CPT (Hub Page)
		if ( ! empty( $country ) ) {
			$existing_country_post = get_page_by_title( $country, OBJECT, 'ef_country' );
			$country_post_id = 0;

			if ( $existing_country_post ) {
				$country_post_id = $existing_country_post->ID;
			} else {
				$country_post_id = wp_insert_post( [
					'post_title'   => $country,
					'post_name'    => sanitize_title( $country ),
					'post_type'    => 'ef_country',
					'post_status'  => 'draft',
				] );
			}

			// Add the new dish to the country's linked dishes
			if ( $country_post_id && ! is_wp_error( $country_post_id ) ) {
				$existing_dish_ids = get_post_meta( $country_post_id, 'ef_dish_ids', true );
				if ( ! is_array( $existing_dish_ids ) ) {
					$existing_dish_ids = [];
				}
				if ( ! in_array( $dish_id, $existing_dish_ids, true ) ) {
					$existing_dish_ids[] = $dish_id;
					update_post_meta( $country_post_id, 'ef_dish_ids', $existing_dish_ids );
				}
			}
		}

		return true;
	}

	public static function ajax_bulk_generate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		AIClient::clear_last_error();

		$country = isset( $_POST['target_country'] ) ? sanitize_text_field( wp_unslash( $_POST['target_country'] ) ) : '';
		
		$success = false;
		if ( empty( $country ) ) {
			// If no country is specified, prioritize draining the pending queue first!
			$success = self::process_pending_holiday();
			if ( ! $success && ! AIClient::was_rate_limited() ) {
				// If queue is empty, fallback to random generation (skip if API is rate-limited).
				$success = self::generate_and_draft_post();
			}
		} else {
			$success = self::generate_and_draft_post( $country );
		}

		if ( $success ) {
			wp_send_json_success( 'Successfully generated item.' );
		}

		if ( AIClient::was_rate_limited() ) {
			wp_send_json_error(
				[
					'code'        => 'rate_limited',
					'message'     => 'Gemini API quota exceeded. Wait before retrying bulk import.',
					'retry_after' => AIClient::get_retry_after_seconds(),
				]
			);
		}

		wp_send_json_error(
			[
				'code'    => 'failed',
				'message' => 'Failed to generate a unique item, or the pending queue is empty.',
			]
		);
	}

	public static function auto_publish_related( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || $post->post_type !== 'ef_dish' ) {
			return;
		}

		// Prevent loops
		remove_action( 'publish_ef_dish', [ self::class, 'auto_publish_related' ], 10 );

		// 1. Auto-publish connected Celebrations
		$celebration_ids = get_post_meta( $post_id, 'ef_celebration_ids', true );
		if ( ! empty( $celebration_ids ) && is_array( $celebration_ids ) ) {
			foreach ( $celebration_ids as $celeb_id ) {
				if ( get_post_status( $celeb_id ) === 'draft' ) {
					wp_publish_post( $celeb_id );
				}
			}
		}

		// 2. Auto-publish connected Country Hub
		$origin = get_post_meta( $post_id, 'ef_origin_country', true );
		if ( ! empty( $origin ) ) {
			$country_post = get_page_by_title( $origin, OBJECT, 'ef_country' );
			if ( $country_post && $country_post->post_status === 'draft' ) {
				wp_publish_post( $country_post->ID );
			}
		}

		add_action( 'publish_ef_dish', [ self::class, 'auto_publish_related' ], 10, 2 );
	}

	public static function init(): void {
		add_action( 'wp_ajax_eatforeign_bulk_generate', [ self::class, 'ajax_bulk_generate' ] );
		add_action( 'wp_ajax_eatforeign_generate_pending_item', [ self::class, 'ajax_generate_pending_item' ] );
		add_action( 'publish_ef_dish', [ self::class, 'auto_publish_related' ], 10, 2 );
	}

	public static function ajax_generate_pending_item(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'code' => 'unauthorized', 'message' => 'Unauthorized.' ] );
		}

		check_ajax_referer( 'eatforeign_generate_pending_item', 'nonce' );

		$pending_id = absint( $_POST['pending_id'] ?? 0 );
		if ( $pending_id <= 0 ) {
			wp_send_json_error( [ 'code' => 'invalid_id', 'message' => 'Missing pending item ID.' ] );
		}

		AIClient::clear_last_error();

		$result = self::process_pending_item( $pending_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		if ( AIClient::was_rate_limited() ) {
			wp_send_json_error(
				[
					'code'        => 'rate_limited',
					'message'     => $result['message'],
					'retry_after' => AIClient::get_retry_after_seconds(),
				]
			);
		}

		wp_send_json_error(
			[
				'code'    => $result['code'] ?? 'failed',
				'message' => $result['message'],
				'dish_id' => $result['dish_id'] ?? 0,
			]
		);
	}

	public static function process_pending_holiday(): bool {
		$args = [
			'post_type'      => 'ef_pending_item',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		$pending_posts = get_posts( $args );

		if ( empty( $pending_posts ) ) {
			return false;
		}

		$result = self::process_pending_item( (int) $pending_posts[0]->ID );

		if ( $result['success'] ) {
			return true;
		}

		// Pending was removed from the queue (e.g. duplicate dish linked) — keep cron/bulk going.
		if ( ! empty( $result['trashed'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate a draft dish (and related content) from one pending queue item.
	 *
	 * @return array{success: bool, message: string, code?: string, dish_id?: int, dish_edit_url?: string, trashed?: bool}
	 */
	public static function process_pending_item( int $pending_id ): array {
		$holiday_post = get_post( $pending_id );

		if (
			! $holiday_post instanceof \WP_Post
			|| $holiday_post->post_type !== 'ef_pending_item'
			|| $holiday_post->post_status !== 'publish'
		) {
			return [
				'success' => false,
				'code'    => 'not_found',
				'message' => 'Pending item not found or already processed.',
			];
		}

		$holiday_title        = $holiday_post->post_title;
		$type                 = get_post_meta( $holiday_post->ID, 'ef_item_type', true );
		$source_url           = get_post_meta( $holiday_post->ID, '_ef_source_url', true );
		$pending_country_name = sanitize_text_field( (string) get_post_meta( $holiday_post->ID, 'ef_country_name', true ) );
		$pending_holiday_raw  = '';

		if ( $type === 'dish' ) {
			Logger::log( "ContentGenerator: Processing pending national dish '{$holiday_title}' from {$pending_country_name}." );
			$data = AIClient::generate_content_from_national_dish( $holiday_title, $pending_country_name );
		} else {
			$pending_holiday_raw = (string) get_post_meta( $holiday_post->ID, 'ef_holiday_date', true );
			Logger::log( "ContentGenerator: Processing pending holiday '{$holiday_title}' for {$pending_holiday_raw}." );
			$data = AIClient::generate_content_from_holiday( $holiday_title, $pending_holiday_raw );
		}
		
		if ( ! $data ) {
			Logger::log( 'ContentGenerator ERROR: Failed to generate content for pending holiday.' );
			$message = 'Failed to generate content. Check API key and eatforeign-api.log.';

			if ( AIClient::was_rate_limited() ) {
				$message = 'Gemini API quota exceeded. Wait before retrying.';
			}

			return [
				'success' => false,
				'code'    => AIClient::was_rate_limited() ? 'rate_limited' : 'ai_failed',
				'message' => $message,
			];
		}

		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( empty( $title ) ) {
			Logger::log( 'ContentGenerator ERROR: AI returned empty dish title. Aborting.' );

			return [
				'success' => false,
				'code'    => 'empty_title',
				'message' => 'AI returned an empty dish title.',
			];
		}

		$existing_dish = get_page_by_title( $title, OBJECT, 'ef_dish' );
		$linked_existing = $existing_dish instanceof \WP_Post;
		$dish_id         = $linked_existing ? (int) $existing_dish->ID : 0;

		if ( $linked_existing ) {
			Logger::log( "ContentGenerator: Dish '{$title}' already exists (ID {$dish_id}); linking celebration from pending queue." );
		} else {
			Logger::log( "ContentGenerator: Proceeding to draft new dish '{$title}'..." );

			$images = ImageClient::search_images( $title . ' food' );

			$dish_id = wp_insert_post( [
				'post_title'   => $title,
				'post_name'    => sanitize_title( $title ),
				'post_type'    => 'ef_dish',
				'post_status'  => 'draft',
			] );

			if ( is_wp_error( $dish_id ) || ! $dish_id ) {
				Logger::log( "ContentGenerator ERROR: Failed to insert dish post '{$title}'." );

				return [
					'success' => false,
					'code'    => 'insert_failed',
					'message' => 'Failed to create the draft dish post.',
				];
			}

			Logger::log( "ContentGenerator: Created Dish draft ID {$dish_id}." );

			update_post_meta( $dish_id, 'ef_cultural_meaning', wp_kses_post( $data['cultural_meaning'] ?? '' ) );
			update_post_meta( $dish_id, 'ef_ingredients', array_map( 'sanitize_text_field', $data['ingredients'] ?? [] ) );
			update_post_meta( $dish_id, 'ef_recipes', array_map( 'esc_url_raw', $data['recipes'] ?? [] ) );
			self::save_suggested_image_sources( $dish_id, $images );

			$spice = sanitize_text_field( $data['spice_level'] ?? '' );
			if ( $spice ) {
				wp_set_object_terms( $dish_id, $spice, 'ef_spice_level' );
			}

			$cuisine = sanitize_text_field( $data['cuisine'] ?? '' );
			if ( $cuisine ) {
				wp_set_object_terms( $dish_id, $cuisine, 'ef_cuisine' );
			}

			$dish_type = sanitize_text_field( $data['dish_type'] ?? '' );
			if ( $dish_type ) {
				wp_set_object_terms( $dish_id, $dish_type, 'ef_dish_type' );
			}

			$dietary_type = sanitize_text_field( $data['dietary_type'] ?? '' );
			if ( $dietary_type ) {
				wp_set_object_terms( $dish_id, $dietary_type, 'ef_dietary_type' );
			}
		}

		$country = self::effective_origin_country( (string) ( $data['origin_country'] ?? '' ), $pending_country_name );

		if ( $country !== '' ) {
			$stored_origin = trim( (string) get_post_meta( $dish_id, 'ef_origin_country', true ) );
			if ( $stored_origin === '' ) {
				update_post_meta( $dish_id, 'ef_origin_country', $country );
			}
			self::append_dish_country_term(
				$dish_id,
				$country,
				sanitize_text_field( $data['country_flag'] ?? '' )
			);
		}

		// Process Celebration
		$celebration_title = sanitize_text_field( $data['celebration_title'] ?? '' );
		if ( $celebration_title === '' && $type !== 'dish' ) {
			$celebration_title = $holiday_title;
		}
		$celebration_id = 0;

		if ( $celebration_title !== '' ) {
			$existing_celebration = get_page_by_title( $celebration_title, OBJECT, 'ef_celebration' );

			if ( $existing_celebration ) {
				$celebration_id = $existing_celebration->ID;
				$existing_dish_ids = get_post_meta( $celebration_id, 'ef_featured_dish_ids', true );
				if ( ! is_array( $existing_dish_ids ) ) {
					$existing_dish_ids = [];
				}
				if ( ! in_array( $dish_id, $existing_dish_ids, true ) ) {
					$existing_dish_ids[] = $dish_id;
					update_post_meta( $celebration_id, 'ef_featured_dish_ids', $existing_dish_ids );
				}
				self::ensure_celebration_event_date(
					$celebration_id,
					(string) ( $data['celebration_date'] ?? '' ),
					$celebration_title,
					$holiday_title,
					$pending_holiday_raw
				);
				update_post_meta( $celebration_id, '_ef_pending_item_id', $holiday_post->ID );
			} else {
				$celebration_id = wp_insert_post( [
					'post_title'   => $celebration_title,
					'post_name'    => sanitize_title( $celebration_title ),
					'post_type'    => 'ef_celebration',
					'post_status'  => 'draft',
				] );

				if ( ! is_wp_error( $celebration_id ) && $celebration_id ) {
					$date = self::resolve_celebration_event_date(
						(string) ( $data['celebration_date'] ?? '' ),
						$celebration_title,
						$holiday_title,
						$pending_holiday_raw
					);
					if ( $date !== '' ) {
						update_post_meta( $celebration_id, 'ef_event_date', $date );
					}
					update_post_meta( $celebration_id, 'ef_featured_dish_ids', [ $dish_id ] );
					update_post_meta( $celebration_id, 'ef_short_description', sanitize_text_field( $data['celebration_short_description'] ?? '' ) );
					update_post_meta( $celebration_id, 'ef_long_description', wp_kses_post( $data['celebration_long_description'] ?? '' ) );
					update_post_meta( $celebration_id, 'ef_hashtags', array_map( 'sanitize_text_field', $data['hashtags'] ?? [] ) );

					if ( $source_url ) {
						update_post_meta( $celebration_id, '_ef_source_url', $source_url );
					}

					update_post_meta( $celebration_id, '_ef_pending_item_id', $holiday_post->ID );

					$celeb_type = sanitize_text_field( $data['celebration_type'] ?? '' );
					if ( $celeb_type ) {
						wp_set_object_terms( $celebration_id, $celeb_type, 'ef_celebration_type' );
					}
				} else {
					$celebration_id = 0;
				}
			}
		}

		if ( $celebration_id > 0 ) {
			self::link_dish_to_celebration( $dish_id, $celebration_id );
		}

		if ( $country !== '' ) {
			self::link_dish_to_country_hub( $dish_id, $country );
		}

		wp_trash_post( $holiday_post->ID );

		$edit_url = get_edit_post_link( $dish_id, 'raw' );

		$message = $linked_existing
			? sprintf( "Linked existing dish '%s' to a new celebration from the pending queue.", $title )
			: sprintf( "Draft dish '%s' created.", $title );

		return [
			'success'       => true,
			'code'          => $linked_existing ? 'duplicate_dish_linked' : 'created',
			'message'       => $message,
			'dish_id'       => (int) $dish_id,
			'dish_edit_url' => is_string( $edit_url ) ? $edit_url : '',
			'trashed'       => true,
		];
	}

	private static function link_dish_to_celebration( int $dish_id, int $celebration_id ): void {
		$celebration_ids = get_post_meta( $dish_id, 'ef_celebration_ids', true );
		if ( ! is_array( $celebration_ids ) ) {
			$celebration_ids = [];
		}
		if ( ! in_array( $celebration_id, $celebration_ids, true ) ) {
			$celebration_ids[] = $celebration_id;
			update_post_meta( $dish_id, 'ef_celebration_ids', array_values( $celebration_ids ) );
		}
	}

	private static function append_dish_country_term( int $dish_id, string $country, string $flag = '' ): void {
		$country = trim( $country );
		if ( $country === '' ) {
			return;
		}

		$term_names = wp_get_post_terms( $dish_id, 'ef_country', [ 'fields' => 'names' ] );
		if ( is_wp_error( $term_names ) ) {
			$term_names = [];
		}
		if ( ! in_array( $country, $term_names, true ) ) {
			$term_names[] = $country;
		}

		$term_ids = wp_set_object_terms( $dish_id, $term_names, 'ef_country' );
		if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) && $flag !== '' ) {
			foreach ( (array) $term_ids as $term_id ) {
				$term = get_term( (int) $term_id, 'ef_country' );
				if ( $term instanceof \WP_Term && $term->name === $country ) {
					update_term_meta( (int) $term->term_id, 'ef_flag_emoji', $flag );
					break;
				}
			}
		}

		self::sync_dish_country_slug_meta( $dish_id );
	}

	private static function link_dish_to_country_hub( int $dish_id, string $country ): void {
		$existing_country_post = get_page_by_title( $country, OBJECT, 'ef_country' );
		$country_post_id       = $existing_country_post instanceof \WP_Post ? $existing_country_post->ID : 0;

		if ( $country_post_id <= 0 ) {
			$country_post_id = wp_insert_post( [
				'post_title'  => $country,
				'post_name'   => sanitize_title( $country ),
				'post_type'   => 'ef_country',
				'post_status' => 'draft',
			] );
		}

		if ( $country_post_id && ! is_wp_error( $country_post_id ) ) {
			$existing_dish_ids = get_post_meta( $country_post_id, 'ef_dish_ids', true );
			if ( ! is_array( $existing_dish_ids ) ) {
				$existing_dish_ids = [];
			}
			if ( ! in_array( $dish_id, $existing_dish_ids, true ) ) {
				$existing_dish_ids[] = $dish_id;
				update_post_meta( $country_post_id, 'ef_dish_ids', $existing_dish_ids );
			}
		}
	}

	private static function resolve_celebration_event_date(
		string $ai_date,
		string $celebration_title,
		string $pending_title = '',
		string $pending_holiday_raw = ''
	): string {
		$date = CelebrationDateRepair::normalize_event_date( $ai_date );
		if ( $date !== '' ) {
			return $date;
		}

		if ( $pending_holiday_raw !== '' ) {
			$date = CelebrationDateRepair::normalize_event_date( $pending_holiday_raw );
			if ( $date !== '' ) {
				return $date;
			}
		}

		$date = CelebrationDateRepair::find_pending_holiday_date( $celebration_title );
		if ( $date !== '' ) {
			return $date;
		}

		if ( $pending_title !== '' && $pending_title !== $celebration_title ) {
			return CelebrationDateRepair::find_pending_holiday_date( $pending_title );
		}

		return '';
	}

	private static function ensure_celebration_event_date(
		int $celebration_id,
		string $ai_date,
		string $celebration_title,
		string $pending_title = '',
		string $pending_holiday_raw = ''
	): void {
		$stored = (string) get_post_meta( $celebration_id, 'ef_event_date', true );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stored ) === 1 ) {
			return;
		}

		$date = self::resolve_celebration_event_date( $ai_date, $celebration_title, $pending_title, $pending_holiday_raw );
		if ( $date !== '' ) {
			update_post_meta( $celebration_id, 'ef_event_date', $date );
		}
	}

	/**
	 * @param list<array<string, string>> $sources
	 */
	private static function save_suggested_image_sources( int $dish_id, array $sources ): void {
		$normalized = [];
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) || empty( $source['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( (string) $source['url'] );
			if ( $url === '' || ! ImageAttribution::is_image_url( $url ) ) {
				continue;
			}

			$normalized[] = [
				'url'           => esc_url_raw( (string) $source['url'] ),
				'sourceType'    => sanitize_key( (string) ( $source['sourceType'] ?? 'remote' ) ),
				'sourceName'    => sanitize_text_field( (string) ( $source['sourceName'] ?? '' ) ),
				'author'        => sanitize_text_field( (string) ( $source['author'] ?? '' ) ),
				'license'       => sanitize_text_field( (string) ( $source['license'] ?? '' ) ),
				'licenseUrl'    => esc_url_raw( (string) ( $source['licenseUrl'] ?? '' ) ),
				'creditPageUrl' => esc_url_raw( (string) ( $source['creditPageUrl'] ?? '' ) ),
			];
		}

		update_post_meta( $dish_id, 'ef_suggested_image_sources', $normalized );
		update_post_meta(
			$dish_id,
			'ef_suggested_images',
			array_values(
				array_unique(
					array_map(
						static fn( array $item ): string => $item['url'],
						$normalized
					)
				)
			)
		);
	}
}
