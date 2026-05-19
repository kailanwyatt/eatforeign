<?php
/**
 * Scheduled blog generation.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class Cron {
	public const HOOK = 'eatforeign_content_daily_blog';

	public static function register(): void {
		add_action( 'init', [ self::class, 'maybe_schedule' ] );
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public static function maybe_schedule(): void {
		if ( get_option( 'eatforeign_content_cron_enabled', '1' ) === '1' ) {
			self::schedule();
		} else {
			self::unschedule();
		}
	}

	public static function run(): void {
		if ( get_option( 'eatforeign_content_cron_enabled', '1' ) !== '1' ) {
			return;
		}

		$api_key = (string) get_option( 'eatforeign_ai_api_key', '' );
		if ( $api_key === '' ) {
			Logger::log( 'Cron: Skipped — Gemini API key not set.' );
			return;
		}

		if ( ! BlogGenerator::check_blog_daily_limit() ) {
			Logger::log( 'Cron: Skipped — daily blog limit reached.' );
			return;
		}

		Logger::log( 'Cron: Starting daily blog draft generation.' );
		$result = BlogGenerator::generate_draft();

		if ( is_wp_error( $result ) ) {
			Logger::log( 'Cron ERROR: ' . $result->get_error_message() );
			return;
		}

		Logger::log( "Cron: Created draft post ID {$result}." );
	}
}
