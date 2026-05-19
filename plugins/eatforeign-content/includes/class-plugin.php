<?php
/**
 * Plugin bootstrap.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class Plugin {
	public static function boot(): void {
		Cron::register();
		Settings::register();
	}

	public static function activate(): void {
		if ( get_option( 'eatforeign_content_daily_limit', '' ) === '' ) {
			update_option( 'eatforeign_content_daily_limit', '1', false );
		}
		if ( get_option( 'eatforeign_content_image_daily_limit', '' ) === '' ) {
			update_option( 'eatforeign_content_image_daily_limit', '3', false );
		}
		if ( get_option( 'eatforeign_content_cron_enabled', '' ) === '' ) {
			update_option( 'eatforeign_content_cron_enabled', '1', false );
		}

		Cron::schedule();
	}

	public static function deactivate(): void {
		Cron::unschedule();
	}
}
