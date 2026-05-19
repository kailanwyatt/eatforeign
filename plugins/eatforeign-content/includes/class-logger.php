<?php
/**
 * Logger for EatForeign Content.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class Logger {
	public static function log( string $message ): void {
		$log_file = EATFOREIGN_CONTENT_DIR . 'eatforeign-content.log';
		$timestamp           = gmdate( 'Y-m-d H:i:s' );
		$formatted_message = "[{$timestamp} UTC] {$message}" . PHP_EOL;

		error_log( $formatted_message, 3, $log_file );
	}
}
