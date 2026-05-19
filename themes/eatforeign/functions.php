<?php
/**
 * Theme bootstrap.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

if (! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/class-helpers.php';
require_once get_template_directory() . '/inc/class-theme.php';
require_once get_template_directory() . '/inc/class-data.php';
require_once get_template_directory() . '/inc/class-template.php';
require_once get_template_directory() . '/inc/class-routes.php';
require_once get_template_directory() . '/inc/class-auth.php';
require_once get_template_directory() . '/inc/class-places.php';

EatForeignTheme\Theme::init();
EatForeignTheme\Routes::init();
EatForeignTheme\Auth::init();
