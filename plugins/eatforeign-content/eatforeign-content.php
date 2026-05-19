<?php
/**
 * Plugin Name: EatForeign Content
 * Description: Automated SEO blog post drafts using Gemini and OpenAI for the EatForeign food celebration platform.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: EatForeign
 * Text Domain: eatforeign-content
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EATFOREIGN_CONTENT_VERSION', '1.0.0' );
define( 'EATFOREIGN_CONTENT_FILE', __FILE__ );
define( 'EATFOREIGN_CONTENT_DIR', plugin_dir_path( __FILE__ ) );

require_once EATFOREIGN_CONTENT_DIR . 'includes/class-logger.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-blog-ai-client.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-blog-topic-resolver.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-blog-image-client.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-blog-generator.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-cron.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-settings.php';
require_once EATFOREIGN_CONTENT_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'EatForeignContent\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'EatForeignContent\Plugin', 'deactivate' ] );

EatForeignContent\Plugin::boot();
