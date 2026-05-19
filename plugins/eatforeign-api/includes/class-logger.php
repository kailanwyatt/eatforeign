<?php
/**
 * Custom Logger
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class Logger {
	public static function log( string $message ): void {
		$log_file = EATFOREIGN_API_DIR . 'eatforeign-api.log';
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$formatted_message = "[{$timestamp} UTC] {$message}" . PHP_EOL;
		
		// Write to the custom log file in the plugin directory
		error_log( $formatted_message, 3, $log_file );
	}
}
