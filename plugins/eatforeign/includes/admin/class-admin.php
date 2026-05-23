<?php
/**
 * Admin editorial UI.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Admin;

use EatForeign\Repositories\ModerationRepository;
use EatForeign\Support\ImageAttribution;
use EatForeign\Support\PostType;

final class Admin {
	public static function boot(): void {
		add_action('add_meta_boxes', [ self::class, 'register_meta_boxes' ] );
		add_action('save_post', [ self::class, 'save_meta_boxes' ] );
		add_filter('manage_' . PostType::CELEBRATION . '_posts_columns', [ self::class, 'celebration_columns' ] );
		add_action('manage_' . PostType::CELEBRATION . '_posts_custom_column', [ self::class, 'render_celebration_column' ], 10, 2 );

		// Moderation columns
		add_filter('manage_' . PostType::DISH . '_posts_columns', [ self::class, 'review_status_column' ] );
		add_action('manage_' . PostType::DISH . '_posts_custom_column', [ self::class, 'render_review_status_column' ], 10, 2 );
		add_filter('manage_' . PostType::CELEBRATION . '_posts_columns', [ self::class, 'review_status_column' ] );
		add_action('manage_' . PostType::CELEBRATION . '_posts_custom_column', [ self::class, 'render_review_status_column' ], 10, 2 );

		// AJAX and Scripts
		add_action('admin_enqueue_scripts', [ self::class, 'enqueue_scripts' ] );
		add_action('wp_ajax_eatforeign_sideload_image', [ self::class, 'ajax_sideload_image' ] );
		add_action('wp_ajax_eatforeign_get_featured_attribution', [ self::class, 'ajax_get_featured_attribution' ] );
		add_action( 'set_post_thumbnail', [ self::class, 'sync_featured_attribution_from_thumbnail' ], 10, 2 );

		add_action('admin_menu', [ self::class, 'register_tools_page' ] );
		add_action('admin_post_ef_remove_imported_mock_data', [ self::class, 'handle_remove_imported_mock_data' ] );
		add_action('admin_notices', [ self::class, 'maybe_show_imported_mock_data_notice' ] );

		add_filter( 'manage_' . PostType::CELEBRATION_POST . '_posts_columns', [ self::class, 'community_moderation_columns' ] );
		add_action( 'manage_' . PostType::CELEBRATION_POST . '_posts_custom_column', [ self::class, 'render_community_moderation_column' ], 10, 2 );
		add_filter( 'manage_' . PostType::COMMENT . '_posts_columns', [ self::class, 'community_moderation_columns' ] );
		add_action( 'manage_' . PostType::COMMENT . '_posts_custom_column', [ self::class, 'render_community_moderation_column' ], 10, 2 );
		add_filter( 'user_row_actions', [ self::class, 'user_row_actions' ], 10, 2 );
		add_action( 'admin_post_ef_moderate_community', [ self::class, 'handle_moderate_community' ] );
		add_action( 'admin_post_ef_toggle_profile_visibility', [ self::class, 'handle_toggle_profile_visibility' ] );
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->post_type !== PostType::DISH ) {
			return;
		}

		wp_enqueue_style(
			'eatforeign-admin-style',
			plugins_url( 'admin-style.css', __FILE__ ),
			[],
			'1.1.0'
		);

		wp_enqueue_script(
			'eatforeign-admin-script',
			plugins_url( 'admin-script.js', __FILE__ ),
			[ 'jquery', 'media-editor' ],
			'1.3.0',
			true
		);

		wp_localize_script( 'eatforeign-admin-script', 'eatforeign_admin', [
			'nonce'          => wp_create_nonce( 'eatforeign_sideload' ),
			'generate_nonce' => wp_create_nonce( 'eatforeign_generate_dish_image' ),
			'has_openai_key' => (string) get_option( 'eatforeign_openai_api_key', '' ) !== '',
			'has_gemini_key' => (string) get_option( 'eatforeign_ai_api_key', '' ) !== '',
		] );
	}

	public static function ajax_sideload_image(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'eatforeign_sideload' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$image_url = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) );
		$post_id   = (int) ( $_POST['post_id'] ?? 0 );

		if ( empty( $image_url ) || empty( $post_id ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		set_post_thumbnail( $post_id, $attachment_id );

		$attribution = self::resolve_attribution_for_sideload( $post_id, $image_url );
		if ( $attribution !== null ) {
			update_post_meta( $post_id, 'ef_featured_image_attribution', $attribution );
			update_post_meta( $attachment_id, '_ef_image_attribution', $attribution );
		}

		$thumbnail_html = _wp_post_thumbnail_html( $attachment_id, $post_id );

		wp_send_json_success(
			array_merge(
				[
					'attachment_id'  => $attachment_id,
					'thumbnail_html' => $thumbnail_html,
				],
				self::attribution_json_payload( $attribution )
			)
		);
	}

	public static function ajax_get_featured_attribution(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'eatforeign_sideload' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$post_id       = (int) ( $_POST['post_id'] ?? 0 );
		$attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );

		if ( $post_id <= 0 || $attachment_id <= 0 ) {
			wp_send_json_error( 'Missing parameters' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$attribution = self::resolve_attribution_for_attachment( $post_id, $attachment_id );
		if ( $attribution !== null ) {
			update_post_meta( $post_id, 'ef_featured_image_attribution', $attribution );
		}

		wp_send_json_success( self::attribution_json_payload( $attribution ) );
	}

	public static function sync_featured_attribution_from_thumbnail( int $post_id, int $thumbnail_id ): void {
		if ( get_post_type( $post_id ) !== PostType::DISH || $thumbnail_id <= 0 ) {
			return;
		}

		$attribution = self::resolve_attribution_for_attachment( $post_id, $thumbnail_id );
		if ( $attribution === null ) {
			return;
		}

		update_post_meta( $post_id, 'ef_featured_image_attribution', $attribution );
		update_post_meta( $thumbnail_id, '_ef_image_attribution', $attribution );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function attribution_json_payload( ?array $attribution ): array {
		if ( $attribution === null || $attribution === [] ) {
			return [
				'attribution'   => null,
				'creditLine'    => '',
				'caption'       => '',
				'isAiGenerated' => false,
			];
		}

		return [
			'attribution'   => $attribution,
			'creditLine'    => ImageAttribution::format_credit_line( $attribution ),
			'caption'       => ImageAttribution::display_caption( $attribution ),
			'isAiGenerated' => ImageAttribution::is_ai_generated( $attribution ),
		];
	}

	/**
	 * @return array<string, string>|null
	 */
	private static function resolve_attribution_for_attachment( int $post_id, int $attachment_id ): ?array {
		$stored = ImageAttribution::normalize_record( get_post_meta( $attachment_id, '_ef_image_attribution', true ) );
		if ( $stored !== [] ) {
			return $stored;
		}

		$image_url = (string) wp_get_attachment_url( $attachment_id );
		if ( $image_url === '' ) {
			return null;
		}

		return self::resolve_attribution_for_url( $post_id, $image_url );
	}

	/**
	 * @return array<string, string>|null
	 */
	private static function resolve_attribution_for_url( int $post_id, string $image_url ): ?array {
		$sources = ImageAttribution::merge_legacy_suggested_urls(
			get_post_meta( $post_id, 'ef_suggested_image_sources', true ),
			get_post_meta( $post_id, 'ef_suggested_images', true )
		);
		$match   = ImageAttribution::find_by_url( $sources, $image_url );
		if ( $match !== null ) {
			return $match;
		}

		$ai_images = get_post_meta( $post_id, 'ef_ai_generated_images', true );
		if ( is_array( $ai_images ) && in_array( $image_url, $ai_images, true ) ) {
			return ImageAttribution::ai_generated_record( $image_url );
		}

		return null;
	}

	/**
	 * @return array<string, string>|null
	 */
	private static function resolve_attribution_for_sideload( int $post_id, string $image_url ): ?array {
		$posted = isset( $_POST['attribution'] ) ? wp_unslash( $_POST['attribution'] ) : '';
		if ( is_string( $posted ) && $posted !== '' ) {
			$decoded = json_decode( $posted, true );
			if ( is_array( $decoded ) ) {
				$record = ImageAttribution::normalize_record( $decoded );
				if ( $record !== [] ) {
					return $record;
				}
			}
		}

		$resolved = self::resolve_attribution_for_url( $post_id, $image_url );
		if ( $resolved !== null ) {
			return $resolved;
		}

		return ImageAttribution::normalize_record(
			[
				'url'        => $image_url,
				'sourceType' => ImageAttribution::TYPE_REMOTE,
				'sourceName' => 'Remote image',
			]
		);
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'eatforeign-celebration-details',
			'Celebration Details',
			[ self::class, 'render_celebration_meta_box' ],
			PostType::CELEBRATION,
			'normal',
			'high'
		);

		add_meta_box(
			'eatforeign-dish-details',
			'Dish Details',
			[ self::class, 'render_dish_meta_box' ],
			PostType::DISH,
			'normal',
			'high'
		);

		add_meta_box(
			'eatforeign-country-details',
			'Country Details',
			[ self::class, 'render_country_meta_box' ],
			PostType::COUNTRY,
			'normal',
			'high'
		);

		add_meta_box(
			'eatforeign-restaurant-details',
			'Restaurant Details',
			[ self::class, 'render_restaurant_meta_box' ],
			PostType::RESTAURANT,
			'normal',
			'high'
		);
	}

	public static function render_celebration_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'eatforeign_save_meta', 'eatforeign_meta_nonce' );
		self::text_field( 'ef_event_date', 'Event date (YYYY-MM-DD)', (string) get_post_meta( $post->ID, 'ef_event_date', true ) );
		self::text_field( 'ef_recurring_rule', 'Recurring rule', (string) get_post_meta( $post->ID, 'ef_recurring_rule', true ) );
		self::editor_field( 'ef_short_description', 'Short description', (string) get_post_meta( $post->ID, 'ef_short_description', true ) );
		self::editor_field( 'ef_long_description', 'Long description', (string) get_post_meta( $post->ID, 'ef_long_description', true ) );
		self::text_field( 'ef_featured_dish_ids', 'Featured dish IDs (comma separated)', implode( ',', array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_featured_dish_ids', true ) ) ) );
		self::text_field( 'ef_hashtags', 'Hashtags (comma separated)', implode( ',', (array) get_post_meta( $post->ID, 'ef_hashtags', true ) ) );
	}

	public static function render_dish_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'eatforeign_save_meta', 'eatforeign_meta_nonce' );
		self::text_field( 'ef_origin_country', 'Origin country', (string) get_post_meta( $post->ID, 'ef_origin_country', true ) );
		self::text_field( 'ef_country_slug', 'Country slug', (string) get_post_meta( $post->ID, 'ef_country_slug', true ) );
		self::editor_field( 'ef_cultural_meaning', 'Cultural meaning', (string) get_post_meta( $post->ID, 'ef_cultural_meaning', true ) );
		self::text_field( 'ef_ingredients', 'Ingredients (comma separated)', implode( ',', (array) get_post_meta( $post->ID, 'ef_ingredients', true ) ) );
		self::text_field( 'ef_gallery_urls', 'Gallery URLs (comma separated)', implode( ',', (array) get_post_meta( $post->ID, 'ef_gallery_urls', true ) ) );
		self::text_field( 'ef_celebration_ids', 'Celebration IDs (comma separated)', implode( ',', array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_celebration_ids', true ) ) ) );

		$has_openai = (string) get_option( 'eatforeign_openai_api_key', '' ) !== '';
		$has_gemini = (string) get_option( 'eatforeign_ai_api_key', '' ) !== '';

		echo '<hr><p><strong>AI-generated photos</strong></p>';
		echo '<p class="description">Generate a photorealistic dish photo with OpenAI or Gemini, then set it as the featured image. Configure API keys under Settings → EatForeign API.</p>';
		echo '<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
		printf(
			'<button type="button" class="button button-secondary ef-generate-dish-image" data-provider="openai" data-post-id="%1$s" %2$s>Generate with OpenAI</button>',
			esc_attr( (string) $post->ID ),
			$has_openai ? '' : 'disabled title="Add OpenAI API key in EatForeign API settings"'
		);
		printf(
			'<button type="button" class="button button-secondary ef-generate-dish-image" data-provider="gemini" data-post-id="%1$s" %2$s>Generate with Gemini</button>',
			esc_attr( (string) $post->ID ),
			$has_gemini ? '' : 'disabled title="Add Gemini API key in EatForeign API settings"'
		);
		echo '<a href="' . esc_url( self::google_images_url_for_dish( $post ) ) . '" target="_blank" rel="noopener noreferrer" class="button button-link">Compare on Google Images</a>';
		echo '</p>';
		echo '<p id="ef-ai-generate-status" style="margin:8px 0;font-weight:600;"></p>';

		$sources_by_url = [];
		foreach ( ImageAttribution::get_suggested_sources( get_post_meta( $post->ID, 'ef_suggested_image_sources', true ) ) as $source ) {
			$sources_by_url[ $source['url'] ] = $source;
		}

		$ai_images = get_post_meta( $post->ID, 'ef_ai_generated_images', true );
		echo '<div id="ef-ai-images-grid" style="display:flex;gap:15px;flex-wrap:wrap;margin-top:10px;">';
		if ( ! empty( $ai_images ) && is_array( $ai_images ) ) {
			foreach ( $ai_images as $url ) {
				$url = (string) $url;
				self::render_image_sideload_card(
					$url,
					$post->ID,
					$sources_by_url[ $url ] ?? ImageAttribution::ai_generated_record( $url )
				);
			}
		}
		echo '</div>';

		$suggested_sources = ImageAttribution::merge_legacy_suggested_urls(
			get_post_meta( $post->ID, 'ef_suggested_image_sources', true ),
			get_post_meta( $post->ID, 'ef_suggested_images', true )
		);
		$wikimedia_sources = array_values(
			array_filter(
				$suggested_sources,
				static fn( array $source ): bool => ( $source['sourceType'] ?? '' ) === ImageAttribution::TYPE_WIKIMEDIA
			)
		);

		if ( $wikimedia_sources !== [] ) {
			echo '<hr><p><strong>Suggested Images from Wikimedia</strong></p>';
			echo '<p class="description">Attribution is stored when you set a photo as featured. Required for license compliance on remote images.</p>';
			echo '<div class="ef-suggested-images-grid" style="display:flex;gap:15px;flex-wrap:wrap;margin-top:10px;">';
			foreach ( $wikimedia_sources as $source ) {
				self::render_image_sideload_card( $source['url'], $post->ID, $source );
			}
			echo '</div>';
		}

		$featured = ImageAttribution::resolve_featured_for_post( $post->ID );
		echo '<hr><p><strong>Featured image attribution</strong></p>';
		echo '<p id="ef-featured-attribution-preview" class="description" style="margin-bottom:8px;' . ( $featured === null ? ' display:none;' : '' ) . '">';
		if ( $featured !== null ) {
			echo esc_html( ImageAttribution::display_caption( $featured ) );
		}
		echo '</p>';
		$has_source_link = is_array( $featured ) && ! empty( $featured['creditPageUrl'] );
		echo '<p id="ef-featured-attribution-source-wrap" style="margin-bottom:8px;' . ( $has_source_link ? '' : ' display:none;' ) . '">';
		echo '<a id="ef-featured-attribution-source-link" href="' . esc_url( $has_source_link ? (string) $featured['creditPageUrl'] : '' ) . '" target="_blank" rel="noopener noreferrer">';
		esc_html_e( 'View source page', 'eatforeign' );
		echo '</a></p>';
		if ( $featured === null ) {
			echo '<p id="ef-featured-attribution-empty" class="description">Set a featured image from the suggestions above to fill in photo credit automatically.</p>';
		}
		echo '<div id="ef-featured-attribution-fields">';
		self::attribution_fields( 'ef_featured_image_attribution', $featured ?? [] );
		echo '</div>';
	}

	public static function google_images_url_for_dish( \WP_Post $post ): string {
		$title  = trim( get_the_title( $post ) );
		$origin = trim( (string) get_post_meta( $post->ID, 'ef_origin_country', true ) );

		$query = $title !== '' ? $title : 'traditional dish';
		if ( $origin !== '' ) {
			$query .= ' ' . $origin;
		}
		$query .= ' food';

		return 'https://www.google.com/search?tbm=isch&q=' . rawurlencode( $query );
	}

	/**
	 * @param array<string, string> $attribution
	 */
	private static function render_image_sideload_card( string $url, int $post_id, array $attribution = [] ): void {
		$credit           = $attribution !== [] ? ImageAttribution::display_caption( $attribution ) : '';
		$is_ai            = $attribution !== [] && ImageAttribution::is_ai_generated( $attribution );
		$attribution_json = $attribution !== [] ? wp_json_encode( $attribution ) : '';

		echo '<div class="ef-image-sideload-card">';
		echo '<img class="ef-image-lightbox-thumb" src="' . esc_url( $url ) . '" data-full-url="' . esc_attr( $url ) . '" alt="' . esc_attr__( 'Click to preview', 'eatforeign' ) . '" title="' . esc_attr__( 'Click to preview', 'eatforeign' ) . '" />';
		if ( $credit !== '' ) {
			$credit_class = 'ef-image-credit description' . ( $is_ai ? ' ef-image-credit--ai' : '' );
			echo '<p class="' . esc_attr( $credit_class ) . '">' . esc_html( $credit ) . '</p>';
		}
		echo '<button type="button" class="button ef-sideload-btn" data-url="' . esc_attr( $url ) . '" data-post-id="' . esc_attr( (string) $post_id ) . '" data-attribution="' . esc_attr( (string) $attribution_json ) . '">Set as Featured</button>';
		echo '</div>';
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function attribution_fields( string $prefix, array $values ): void {
		self::text_field( $prefix . '_source_name', 'Source name', (string) ( $values['sourceName'] ?? '' ) );
		self::text_field( $prefix . '_author', 'Author / creator', (string) ( $values['author'] ?? '' ) );
		self::text_field( $prefix . '_license', 'License', (string) ( $values['license'] ?? '' ) );
		self::text_field( $prefix . '_license_url', 'License URL', (string) ( $values['licenseUrl'] ?? '' ) );
		self::text_field( $prefix . '_credit_page_url', 'Credit / source page URL', (string) ( $values['creditPageUrl'] ?? '' ) );
	}

	public static function render_country_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'eatforeign_save_meta', 'eatforeign_meta_nonce' );
		self::editor_field( 'ef_overview', 'Overview', (string) get_post_meta( $post->ID, 'ef_overview', true ) );
		self::text_field( 'ef_hero_image_url', 'Hero image URL', (string) get_post_meta( $post->ID, 'ef_hero_image_url', true ) );
		self::text_field( 'ef_dish_ids', 'Dish IDs (comma separated)', implode( ',', array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_dish_ids', true ) ) ) );
		self::text_field( 'ef_celebration_ids', 'Celebration IDs (comma separated)', implode( ',', array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_celebration_ids', true ) ) ) );
	}

	public static function render_restaurant_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'eatforeign_save_meta', 'eatforeign_meta_nonce' );
		self::text_field( 'ef_address', 'Address', (string) get_post_meta( $post->ID, 'ef_address', true ) );
		self::text_field( 'ef_city', 'City', (string) get_post_meta( $post->ID, 'ef_city', true ) );
		self::text_field( 'ef_state', 'State', (string) get_post_meta( $post->ID, 'ef_state', true ) );
		self::text_field( 'ef_country', 'Country', (string) get_post_meta( $post->ID, 'ef_country', true ) );
		self::text_field( 'ef_latitude', 'Latitude', (string) get_post_meta( $post->ID, 'ef_latitude', true ) );
		self::text_field( 'ef_longitude', 'Longitude', (string) get_post_meta( $post->ID, 'ef_longitude', true ) );
		self::text_field( 'ef_website', 'Website', (string) get_post_meta( $post->ID, 'ef_website', true ) );
		self::text_field( 'ef_image_url', 'Image URL', (string) get_post_meta( $post->ID, 'ef_image_url', true ) );
		self::text_field( 'ef_dish_ids', 'Dish IDs (comma separated)', implode( ',', array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_dish_ids', true ) ) ) );
		self::text_field( 'ef_celebration_ids', 'Celebration IDs (comma separated)', implode( ',', array_map( 'strval', (array) get_post_meta( $post->ID, 'ef_celebration_ids', true ) ) ) );
	}

	public static function save_meta_boxes( int $post_id ): void {
		if (! isset( $_POST['eatforeign_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eatforeign_meta_nonce'] ) ), 'eatforeign_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if (! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array_keys( $_POST );
		foreach ( $fields as $field ) {
			if ( ! str_starts_with( $field, 'ef_' ) || str_starts_with( $field, 'ef_featured_image_attribution_' ) ) {
				continue;
			}

			$value = wp_unslash( $_POST[ $field ] );
			update_post_meta( $post_id, $field, is_array( $value ) ? $value : sanitize_text_field( (string) $value ) );
		}

		if ( get_post_type( $post_id ) === PostType::DISH ) {
			self::save_featured_attribution_from_post( $post_id );
		}
	}

	private static function save_featured_attribution_from_post( int $post_id ): void {
		$has_input = false;
		foreach ( [ 'source_name', 'author', 'license', 'license_url', 'credit_page_url' ] as $suffix ) {
			$key = 'ef_featured_image_attribution_' . $suffix;
			if ( isset( $_POST[ $key ] ) && trim( (string) wp_unslash( $_POST[ $key ] ) ) !== '' ) {
				$has_input = true;
				break;
			}
		}

		$existing = ImageAttribution::get_featured_attribution( get_post_meta( $post_id, 'ef_featured_image_attribution', true ) );
		$image_url = (string) ( get_the_post_thumbnail_url( $post_id, 'full' ) ?: ( $existing['url'] ?? '' ) );

		if ( ! $has_input && $existing === null ) {
			return;
		}

		$record = ImageAttribution::normalize_record(
			[
				'url'           => $image_url,
				'sourceType'    => $existing['sourceType'] ?? ImageAttribution::TYPE_MANUAL,
				'sourceName'    => sanitize_text_field( (string) wp_unslash( $_POST['ef_featured_image_attribution_source_name'] ?? '' ) ),
				'author'        => sanitize_text_field( (string) wp_unslash( $_POST['ef_featured_image_attribution_author'] ?? '' ) ),
				'license'       => sanitize_text_field( (string) wp_unslash( $_POST['ef_featured_image_attribution_license'] ?? '' ) ),
				'licenseUrl'    => esc_url_raw( (string) wp_unslash( $_POST['ef_featured_image_attribution_license_url'] ?? '' ) ),
				'creditPageUrl' => esc_url_raw( (string) wp_unslash( $_POST['ef_featured_image_attribution_credit_page_url'] ?? '' ) ),
			]
		);

		if ( $record === [] ) {
			delete_post_meta( $post_id, 'ef_featured_image_attribution' );
			return;
		}

		update_post_meta( $post_id, 'ef_featured_image_attribution', $record );
	}

	/**
	 * @param list<string> $columns
	 * @return list<string>
	 */
	public static function celebration_columns( array $columns ): array {
		$columns['ef_event_date'] = 'Event date';
		return $columns;
	}

	public static function render_celebration_column( string $column, int $post_id ): void {
		if ( $column === 'ef_event_date' ) {
			echo esc_html( (string) get_post_meta( $post_id, 'ef_event_date', true ) );
		}
	}

	public static function review_status_column( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( $key === 'title' ) {
				$new_columns['ef_review_status'] = 'Review Status';
			}
		}
		return $new_columns;
	}

	public static function render_review_status_column( string $column, int $post_id ): void {
		if ( $column === 'ef_review_status' ) {
			$status = get_post_status( $post_id );
			if ( $status === 'draft' ) {
				echo '<span style="display:inline-block;padding:3px 8px;background:#f0c24b;color:#000;border-radius:3px;font-weight:bold;font-size:11px;">Needs Review</span>';
			} elseif ( $status === 'publish' ) {
				echo '<span style="display:inline-block;padding:3px 8px;background:#46b450;color:#fff;border-radius:3px;font-weight:bold;font-size:11px;">Published</span>';
			} else {
				echo esc_html( ucfirst( $status ) );
			}
		}
	}

	private static function text_field( string $name, string $label, string $value ): void {
		echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br />';
		echo '<input type="text" class="widefat" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></p>';
	}

	private static function textarea_field( string $name, string $label, string $value ): void {
		echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br />';
		echo '<textarea class="widefat" rows="4" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea></p>';
	}

	private static function editor_field( string $name, string $label, string $value ): void {
		echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label></p>';
		wp_editor( $value, $name, [
			'textarea_name' => $name,
			'media_buttons' => false,
			'textarea_rows' => 8,
			'teeny'         => true,
		] );
	}

	public static function register_tools_page(): void {
		if ( ! MockDataCleanup::has_seeded_content() ) {
			return;
		}

		add_management_page(
			__( 'Imported mock data', 'eatforeign' ),
			__( 'Imported mock data', 'eatforeign' ),
			'manage_options',
			'eatforeign-imported-mock-data',
			[ self::class, 'render_tools_page' ]
		);
	}

	public static function render_tools_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage imported mock data.', 'eatforeign' ) );
		}

		$counts = MockDataCleanup::get_seed_counts();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Imported mock data', 'eatforeign' ); ?></h1>
			<p><?php echo esc_html__( 'Remove catalog, community, and passport content that was imported by the retired EatForeign Mock Data plugin.', 'eatforeign' ); ?></p>
			<ul>
				<li><?php echo esc_html( sprintf( __( 'Countries: %d', 'eatforeign' ), $counts['countries'] ?? 0 ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Dishes: %d', 'eatforeign' ), $counts['dishes'] ?? 0 ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Celebrations: %d', 'eatforeign' ), $counts['celebrations'] ?? 0 ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Restaurants: %d', 'eatforeign' ), $counts['restaurants'] ?? 0 ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Celebration posts: %d', 'eatforeign' ), $counts['celebration_posts'] ?? 0 ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Comments: %d', 'eatforeign' ), $counts['comments'] ?? 0 ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Demo users: %d', 'eatforeign' ), $counts['users'] ?? 0 ) ); ?></li>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ef_remove_imported_mock_data' ); ?>
				<input type="hidden" name="action" value="ef_remove_imported_mock_data" />
				<?php submit_button( __( 'Remove imported mock data', 'eatforeign' ), 'delete', 'submit', false, [ 'onclick' => "return confirm('" . esc_js( __( 'Delete all imported mock data? This cannot be undone.', 'eatforeign' ) ) . "');" ] ); ?>
			</form>
		</div>
		<?php
	}

	public static function maybe_show_imported_mock_data_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['ef_notice'] ) || sanitize_key( wp_unslash( (string) $_GET['ef_notice'] ) ) !== 'removed' ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'Imported mock data removed successfully.', 'eatforeign' );
		echo '</p></div>';
	}

	public static function handle_remove_imported_mock_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage imported mock data.', 'eatforeign' ) );
		}

		check_admin_referer( 'ef_remove_imported_mock_data' );

		MockDataCleanup::remove();

		wp_safe_redirect(
			add_query_arg(
				'ef_notice',
				'removed',
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function community_moderation_columns( array $columns ): array {
		$columns['ef_visibility'] = 'Moderation';

		return $columns;
	}

	public static function render_community_moderation_column( string $column, int $post_id ): void {
		if ( $column !== 'ef_visibility' ) {
			return;
		}

		$status = (string) get_post_meta( $post_id, 'ef_visibility', true );

		if ( $status === '' ) {
			$status = get_post_status( $post_id ) === 'publish' ? ModerationRepository::STATUS_APPROVED : ModerationRepository::STATUS_PENDING;
		}

		echo esc_html( ucfirst( $status ) );

		if ( current_user_can( 'eatforeign_moderate_community' ) ) {
			$base = admin_url( 'admin-post.php' );
			echo '<div style="display:flex;gap:6px;margin-top:6px;">';
			foreach ( [ ModerationRepository::STATUS_APPROVED, ModerationRepository::STATUS_REJECTED, ModerationRepository::STATUS_PENDING ] as $action ) {
				$url = wp_nonce_url(
					add_query_arg(
						[
							'action' => 'ef_moderate_community',
							'post'   => $post_id,
							'status' => $action,
						],
						$base
					),
					'ef_moderate_community_' . $post_id
				);
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( ucfirst( $action ) ) . '</a>';
			}
			echo '</div>';
		}
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public static function user_row_actions( array $actions, \WP_User $user ): array {
		if ( ! current_user_can( 'eatforeign_moderate_community' ) ) {
			return $actions;
		}

		$public = ModerationRepository::is_profile_public( $user->ID );
		$url    = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'ef_toggle_profile_visibility',
					'user'   => $user->ID,
					'public' => $public ? '0' : '1',
				],
				admin_url( 'admin-post.php' )
			),
			'ef_toggle_profile_visibility_' . $user->ID
		);

		$actions['ef_profile_visibility'] = '<a href="' . esc_url( $url ) . '">' . esc_html( $public ? 'Hide passport' : 'Show passport' ) . '</a>';

		return $actions;
	}

	public static function handle_moderate_community(): void {
		if ( ! current_user_can( 'eatforeign_moderate_community' ) ) {
			wp_die( esc_html__( 'You do not have permission to moderate community content.', 'eatforeign' ) );
		}

		$post_id = absint( $_GET['post'] ?? 0 );
		check_admin_referer( 'ef_moderate_community_' . $post_id );

		$status = ModerationRepository::normalize_status( (string) ( $_GET['status'] ?? '' ) );
		ModerationRepository::set_post_visibility( $post_id, $status );

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php?post_type=ef_celebration_post' ) );
		exit;
	}

	public static function handle_toggle_profile_visibility(): void {
		if ( ! current_user_can( 'eatforeign_moderate_community' ) ) {
			wp_die( esc_html__( 'You do not have permission to moderate profiles.', 'eatforeign' ) );
		}

		$user_id = absint( $_GET['user'] ?? 0 );
		check_admin_referer( 'ef_toggle_profile_visibility_' . $user_id );

		ModerationRepository::set_profile_public( $user_id, ( $_GET['public'] ?? '0' ) === '1' );

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'users.php' ) );
		exit;
	}
}
