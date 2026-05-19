<?php
/**
 * Plugin bootstrap.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign;

use EatForeign\Admin\Admin;
use EatForeign\GraphQL\GraphQL;
use EatForeign\Meta\Meta;
use EatForeign\PostTypes\PostTypes;
use EatForeign\REST\REST;
use EatForeign\Support\AuthToken;
use EatForeign\Support\Capabilities;
use EatForeign\Support\Notifications;
use EatForeign\Taxonomies\Taxonomies;

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		$this->includes();

		add_action( 'init', [ PostTypes::class, 'register' ], 0 );
		add_action( 'init', [ Taxonomies::class, 'register' ], 0 );
		add_action( 'init', [ Capabilities::class, 'register' ], 5 );
		add_action( 'init', [ AuthToken::class, 'register' ], 1 );
		add_action( 'init', [ Meta::class, 'register' ], 20 );
		add_action( 'init', [ REST::class, 'register' ], 20 );
		add_action( 'init', [ Notifications::class, 'register' ], 20 );

		if ( is_admin() ) {
			Admin::boot();
		}

		add_action( 'graphql_register_types', [ GraphQL::class, 'register' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_graphql_notice' ] );
	}

	private function includes(): void {
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/post-types/class-post-types.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/taxonomies/class-taxonomies.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/meta/class-meta.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/admin/class-mock-data-cleanup.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/rest/class-rest.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/rest/class-auth-controller.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/rest/class-bootstrap-controller.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/rest/class-account-controller.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/rest/class-social-controller.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/graphql/class-graphql.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/graphql/class-types.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/graphql/class-queries.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/graphql/class-mutations.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/repositories/class-catalog-repository.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/repositories/class-community-repository.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/repositories/class-moderation-repository.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/repositories/class-passport-repository.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/support/class-post-type.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/support/class-sanitizer.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/support/class-capabilities.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/support/class-auth-token.php';
		require_once EATFOREIGN_PLUGIN_DIR . 'includes/support/class-notifications.php';
	}

	public function maybe_show_graphql_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || class_exists( 'WPGraphQL' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'EatForeign GraphQL endpoints require WPGraphQL. Install and activate WPGraphQL to expose the API to web and mobile clients.',
			'eatforeign'
		);
		echo '</p></div>';
	}
}
