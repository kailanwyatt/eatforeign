<?php
/**
 * Cron Scheduling
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class Cron {
	public static function register(): void {
		add_action( 'init', [ self::class, 'schedule_cron' ] );
		add_action( 'eatforeign_api_daily_generation', [ self::class, 'run_generation' ] );
		add_action( 'admin_post_eatforeign_api_manual_generation', [ self::class, 'run_manual_generation' ] );
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'eatforeign_api_daily_generation' ) ) {
			wp_schedule_event( time(), 'daily', 'eatforeign_api_daily_generation' );
		}
	}

	public static function run_generation(): void {
		// Only run if API keys are set
		$ai_key = get_option( 'eatforeign_ai_api_key' );
		if ( empty( $ai_key ) ) {
			return;
		}

		// Process pending queue items (continues after duplicate-dish links).
		for ( $i = 0; $i < 300; $i++ ) {
			$processed = ContentGenerator::process_pending_holiday();
			if ( ! $processed ) {
				break; // Queue is empty or failed
			}
			sleep( 2 ); // slight delay between API calls
		}

		// Generate one random dish if the queue is empty
		ContentGenerator::generate_and_draft_post();
	}

	public static function run_manual_generation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'eatforeign_api_manual_gen_nonce', 'eatforeign_api_nonce' );

		$processed = ContentGenerator::process_pending_holiday();
		if ( ! $processed ) {
			ContentGenerator::generate_and_draft_post();
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=eatforeign-api&generated=1' ) );
		exit;
	}
}
