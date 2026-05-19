<?php
/**
 * Orchestrates SEO blog draft creation.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class BlogGenerator {
	public static function generate_draft(): int|\WP_Error {
		if ( ! self::check_blog_daily_limit() ) {
			return new \WP_Error( 'eatforeign_content_blog_limit', 'Daily blog draft limit reached.' );
		}

		$api_key = (string) get_option( 'eatforeign_ai_api_key', '' );
		if ( $api_key === '' ) {
			return new \WP_Error( 'eatforeign_content_missing_gemini', 'Gemini API key is not configured in EatForeign API settings.' );
		}

		BlogAiClient::clear_last_error();
		$topic   = BlogTopicResolver::resolve_next();
		$article = BlogAiClient::generate_article( $topic );

		if ( $article === null ) {
			if ( BlogAiClient::was_rate_limited() ) {
				return new \WP_Error(
					'rate_limited',
					'Gemini API quota exceeded.',
					[ 'retry_after' => BlogAiClient::get_retry_after_seconds() ]
				);
			}
			return new \WP_Error( 'eatforeign_content_ai_failed', 'Failed to generate article content from Gemini.' );
		}

		$post_id = self::create_post_from_article( $article, $topic );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$image_prompt = sanitize_text_field( (string) ( $article['image_prompt'] ?? '' ) );
		if ( $image_prompt === '' ) {
			$image_prompt = BlogImageClient::build_fallback_prompt( get_the_title( $post_id ) );
		}

		$image_result = BlogImageClient::generate_and_attach( $post_id, $image_prompt );
		if ( is_wp_error( $image_result ) ) {
			update_post_meta( $post_id, 'ef_blog_needs_image', '1' );
			Logger::log( 'BlogGenerator: Draft created without featured image — ' . $image_result->get_error_message() );
		}

		self::increment_blog_daily_count();
		Logger::log( "BlogGenerator: Created draft post ID {$post_id}." );

		return $post_id;
	}

	/**
	 * @param array<string, mixed> $article
	 * @param array<string, mixed> $topic
	 */
	private static function create_post_from_article( array $article, array $topic ): int|\WP_Error {
		$title   = sanitize_text_field( (string) ( $article['title'] ?? '' ) );
		$content = wp_kses_post( (string) ( $article['content_html'] ?? '' ) );
		$excerpt = sanitize_textarea_field( (string) ( $article['excerpt'] ?? '' ) );
		$slug    = sanitize_title( (string) ( $article['slug'] ?? $title ) );

		if ( $title === '' || $content === '' ) {
			return new \WP_Error( 'eatforeign_content_invalid_article', 'Generated article missing title or content.' );
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_type'    => 'post',
				'post_status'  => 'draft',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		$source_type = (string) ( $topic['type'] ?? 'freeform' );
		update_post_meta( $post_id, 'ef_blog_source_type', $source_type );

		if ( $source_type === 'celebration' && ! empty( $topic['celebration']['id'] ) ) {
			update_post_meta( $post_id, 'ef_blog_source_celebration_id', (int) $topic['celebration']['id'] );
		}
		if ( ! empty( $topic['dish']['id'] ) ) {
			update_post_meta( $post_id, 'ef_blog_source_dish_id', (int) $topic['dish']['id'] );
		}

		$meta_desc = sanitize_text_field( (string) ( $article['meta_description'] ?? '' ) );
		$focus_kw  = sanitize_text_field( (string) ( $article['focus_keyword'] ?? '' ) );
		update_post_meta( $post_id, 'ef_meta_description', $meta_desc );
		update_post_meta( $post_id, 'ef_focus_keyword', $focus_kw );

		if ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_kw );
		}

		self::assign_category( $post_id );
		self::assign_tags( $post_id, $article['tags'] ?? [] );

		return $post_id;
	}

	private static function assign_category( int $post_id ): void {
		$cat_id = (int) get_option( 'eatforeign_content_default_category', 0 );
		if ( $cat_id <= 0 ) {
			$existing = get_category_by_slug( 'food-culture' );
			if ( $existing ) {
				$cat_id = (int) $existing->term_id;
			} else {
				$created = wp_insert_term( 'Food Culture', 'category', [ 'slug' => 'food-culture' ] );
				if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$cat_id = (int) $created['term_id'];
				}
			}
			if ( $cat_id > 0 ) {
				update_option( 'eatforeign_content_default_category', (string) $cat_id, false );
			}
		}
		if ( $cat_id > 0 ) {
			wp_set_post_categories( $post_id, [ $cat_id ] );
		}
	}

	/**
	 * @param mixed $tags
	 */
	private static function assign_tags( int $post_id, $tags ): void {
		if ( ! is_array( $tags ) || $tags === [] ) {
			return;
		}
		$names = array_map(
			static fn ( $t ): string => sanitize_text_field( (string) $t ),
			$tags
		);
		$names = array_values( array_filter( $names ) );
		if ( $names !== [] ) {
			wp_set_post_tags( $post_id, $names, false );
		}
	}

	public static function check_blog_daily_limit(): bool {
		$limit = (int) get_option( 'eatforeign_content_daily_limit', 1 );
		if ( $limit <= 0 ) {
			return true;
		}
		$count_key = 'ef_content_blog_count_' . gmdate( 'Y_m_d' );
		return (int) get_transient( $count_key ) < $limit;
	}

	private static function increment_blog_daily_count(): void {
		$count_key = 'ef_content_blog_count_' . gmdate( 'Y_m_d' );
		$current   = (int) get_transient( $count_key );
		set_transient( $count_key, $current + 1, DAY_IN_SECONDS );
	}

	public static function ajax_generate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'code' => 'unauthorized', 'message' => 'Unauthorized' ] );
		}

		$result = self::generate_draft();

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$data = [
				'code'    => $code === 'rate_limited' ? 'rate_limited' : 'failed',
				'message' => $result->get_error_message(),
			];
			if ( $code === 'rate_limited' ) {
				$error_data = $result->get_error_data();
				$data['retry_after'] = is_array( $error_data ) && isset( $error_data['retry_after'] )
					? (int) $error_data['retry_after']
					: BlogAiClient::get_retry_after_seconds();
			}
			wp_send_json_error( $data );
		}

		wp_send_json_success(
			[
				'post_id' => $result,
				'edit_url' => get_edit_post_link( $result, 'raw' ),
				'message'  => 'Blog draft created successfully.',
			]
		);
	}
}
