<?php
/**
 * Plugin Name: EatForeign
 * Description: API-first food celebration calendar backend for the EatForeign web and mobile apps.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: EatForeign
 * Text Domain: eatforeign
 *
 * @package EatForeign
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('EATFOREIGN_VERSION', '0.1.0');
define('EATFOREIGN_PLUGIN_FILE', __FILE__);
define('EATFOREIGN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EATFOREIGN_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EATFOREIGN_PLUGIN_DIR . 'includes/class-plugin.php';

EatForeign\Plugin::instance()->boot();
