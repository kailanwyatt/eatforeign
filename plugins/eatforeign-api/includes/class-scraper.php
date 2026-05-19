<?php
/**
 * Scraper for Food Holidays
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class Scraper {
	public static function register(): void {
		add_action( 'wp_ajax_eatforeign_scrape_url', [ self::class, 'ajax_scrape_url' ] );
	}

	public static function scrape_and_queue( string $url, string $type ): int {
		Logger::log( "Scraper: Fetching URL: {$url} with type: {$type}" );

		$response = wp_remote_get( $url, [
			'timeout' => 30,
			'headers' => [
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
			]
		] );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'Scraper ERROR: Failed to fetch ' . $url . ' - ' . $response->get_error_message() );
			return 0;
		}

		$body = wp_remote_retrieve_body( $response );
		
		// Strip unnecessary tags to reduce token size
		$body = preg_replace( '@<script[^>]*?>.*?</script>@si', '', $body );
		$body = preg_replace( '@<style[^>]*?>.*?</style>@si', '', $body );
		$text = wp_strip_all_tags( $body );
		
		// Optional: clean up excessive whitespace
		$text = preg_replace('/\s+/', ' ', $text);

		$items = [];
		if ( $type === 'dish' ) {
			$items = AIClient::extract_dishes_from_text( $text );
		} else {
			$items = AIClient::extract_holidays_from_text( $text );
		}

		if ( empty( $items ) ) {
			Logger::log( "Scraper ERROR: No items extracted from {$url}." );
			return 0;
		}

		$count = 0;
		foreach ( $items as $item ) {
			$title = sanitize_text_field( $item['title'] ?? '' );

			if ( empty( $title ) ) {
				continue;
			}

			// Check if we already have this in pending queue or as a published celebration/dish
			$existing_pending = get_page_by_title( $title, OBJECT, 'ef_pending_item' );
			$existing_celeb = get_page_by_title( $title, OBJECT, 'ef_celebration' );
			$existing_dish = get_page_by_title( $title, OBJECT, 'ef_dish' );

			if ( $existing_pending || $existing_celeb || $existing_dish ) {
				continue; // Skip duplicates
			}

			$post_id = wp_insert_post( [
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_type'    => 'ef_pending_item',
			] );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, 'ef_item_type', $type );
				update_post_meta( $post_id, '_ef_source_url', esc_url_raw( $url ) );
				
				if ( $type === 'dish' ) {
					$country = sanitize_text_field( $item['country'] ?? '' );
					update_post_meta( $post_id, 'ef_country_name', $country );
				} else {
					$date = sanitize_text_field( $item['date'] ?? '' );
					update_post_meta( $post_id, 'ef_holiday_date', $date );
				}
				
				$count++;
			}
		}

		Logger::log( "Scraper: Queued {$count} new {$type} items from {$url}." );
		return $count;
	}

	public static function ajax_scrape_url(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		// Prevent PHP execution timeout since scraping and AI extraction can take a while
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'holiday';

		if ( empty( $url ) ) {
			wp_send_json_error( 'Invalid URL' );
		}

		$count = self::scrape_and_queue( $url, $type );

		if ( $count >= 0 ) {
			wp_send_json_success( [ 'count' => $count ] );
		} else {
			wp_send_json_error( 'Failed to scrape URL.' );
		}
	}
}
