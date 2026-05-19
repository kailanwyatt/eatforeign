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
		update_post_meta( $dish_id, 'ef_suggested_images', array_map( 'esc_url_raw', $images ) );
		
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
			} else {
				$celebration_id = wp_insert_post( [
					'post_title'   => $celebration_title,
					'post_name'    => sanitize_title( $celebration_title ),
					'post_type'    => 'ef_celebration',
					'post_status'  => 'draft',
				] );

				if ( ! is_wp_error( $celebration_id ) && $celebration_id ) {
					$date = sanitize_text_field( $data['celebration_date'] ?? '' );
					update_post_meta( $celebration_id, 'ef_event_date', $date );
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
		add_action( 'publish_ef_dish', [ self::class, 'auto_publish_related' ], 10, 2 );
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
			return false; // No pending items
		}

		$holiday_post = $pending_posts[0];
		$holiday_title = $holiday_post->post_title;
		$type = get_post_meta( $holiday_post->ID, 'ef_item_type', true );
		$source_url = get_post_meta( $holiday_post->ID, '_ef_source_url', true );
		$pending_country_name = sanitize_text_field( (string) get_post_meta( $holiday_post->ID, 'ef_country_name', true ) );

		if ( $type === 'dish' ) {
			Logger::log( "ContentGenerator: Processing pending national dish '{$holiday_title}' from {$pending_country_name}." );
			$data = AIClient::generate_content_from_national_dish( $holiday_title, $pending_country_name );
		} else {
			$holiday_date = get_post_meta( $holiday_post->ID, 'ef_holiday_date', true );
			Logger::log( "ContentGenerator: Processing pending holiday '{$holiday_title}' for {$holiday_date}." );
			$data = AIClient::generate_content_from_holiday( $holiday_title, (string) $holiday_date );
		}
		
		if ( ! $data ) {
			Logger::log( 'ContentGenerator ERROR: Failed to generate content for pending holiday.' );
			return false;
		}

		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( empty( $title ) ) {
			Logger::log( 'ContentGenerator ERROR: AI returned empty dish title. Aborting.' );
			return false;
		}

		// Check if dish already exists
		$existing_dish = get_page_by_title( $title, OBJECT, 'ef_dish' );
		if ( $existing_dish ) {
			Logger::log( "ContentGenerator SKIP: Dish '{$title}' already exists." );
			// Mark pending as processed anyway to avoid infinite loop
			wp_trash_post( $holiday_post->ID );
			return false; 
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

		$country = self::effective_origin_country( (string) ( $data['origin_country'] ?? '' ), $pending_country_name );

		// Save Meta
		update_post_meta( $dish_id, 'ef_origin_country', $country );
		update_post_meta( $dish_id, 'ef_cultural_meaning', wp_kses_post( $data['cultural_meaning'] ?? '' ) );
		update_post_meta( $dish_id, 'ef_ingredients', array_map( 'sanitize_text_field', $data['ingredients'] ?? [] ) );
		update_post_meta( $dish_id, 'ef_recipes', array_map( 'esc_url_raw', $data['recipes'] ?? [] ) );
		update_post_meta( $dish_id, 'ef_suggested_images', array_map( 'esc_url_raw', $images ) );
		
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
			} else {
				$celebration_id = wp_insert_post( [
					'post_title'   => $celebration_title,
					'post_name'    => sanitize_title( $celebration_title ),
					'post_type'    => 'ef_celebration',
					'post_status'  => 'draft',
				] );

				if ( ! is_wp_error( $celebration_id ) && $celebration_id ) {
					$date = sanitize_text_field( $data['celebration_date'] ?? '' );
					update_post_meta( $celebration_id, 'ef_event_date', $date );
					update_post_meta( $celebration_id, 'ef_featured_dish_ids', [ $dish_id ] );
					update_post_meta( $celebration_id, 'ef_short_description', sanitize_text_field( $data['celebration_short_description'] ?? '' ) );
					update_post_meta( $celebration_id, 'ef_long_description', wp_kses_post( $data['celebration_long_description'] ?? '' ) );
					update_post_meta( $celebration_id, 'ef_hashtags', array_map( 'sanitize_text_field', $data['hashtags'] ?? [] ) );
					
					if ( $source_url ) {
						update_post_meta( $celebration_id, '_ef_source_url', $source_url );
					}
					
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

		// Trash the pending holiday now that it is processed
		wp_trash_post( $holiday_post->ID );
		
		return true;
	}
}
