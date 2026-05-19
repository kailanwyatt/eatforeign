<?php
/**
 * Uninstall cleanup.
 *
 * @package EatForeign
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('eatforeign_version');
