<?php
/**
 * Plugin Name: EatForeign API
 * Description: Connects to AI and Google Places to auto-generate content and fetch restaurants for EatForeign.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: EatForeign
 * Text Domain: eatforeign-api
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EATFOREIGN_API_VERSION', '0.1.0' );
define( 'EATFOREIGN_API_FILE', __FILE__ );
define( 'EATFOREIGN_API_DIR', plugin_dir_path( __FILE__ ) );

// Load classes
require_once EATFOREIGN_API_DIR . 'includes/class-logger.php';
require_once EATFOREIGN_API_DIR . 'includes/class-settings.php';
require_once EATFOREIGN_API_DIR . 'includes/class-pending-holiday.php';
require_once EATFOREIGN_API_DIR . 'includes/class-ai-client.php';
require_once EATFOREIGN_API_DIR . 'includes/class-image-client.php';
require_once EATFOREIGN_API_DIR . 'includes/class-openai-image-client.php';
require_once EATFOREIGN_API_DIR . 'includes/class-places-client.php';
require_once EATFOREIGN_API_DIR . 'includes/class-scraper.php';
require_once EATFOREIGN_API_DIR . 'includes/class-content-generator.php';
require_once EATFOREIGN_API_DIR . 'includes/class-cron.php';
require_once EATFOREIGN_API_DIR . 'includes/class-rest-api.php';

// Initialize
EatForeignAPI\Settings::register();
EatForeignAPI\PendingItem::register();
EatForeignAPI\Scraper::register();
EatForeignAPI\Cron::register();
EatForeignAPI\RestAPI::register();
EatForeignAPI\ContentGenerator::init();
