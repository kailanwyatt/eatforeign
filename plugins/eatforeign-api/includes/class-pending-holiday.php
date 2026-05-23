<?php
/**
 * Pending Item Post Type
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class PendingItem {
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_post_type' ] );
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_boxes' ] );
		add_filter( 'manage_ef_pending_item_posts_columns', [ self::class, 'set_custom_columns' ] );
		add_action( 'manage_ef_pending_item_posts_custom_column', [ self::class, 'render_custom_columns' ], 10, 2 );
		add_filter( 'post_row_actions', [ self::class, 'row_actions' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_scripts' ] );
	}

	public static function register_post_type(): void {
		$args = [
			'labels'             => [
				'name'               => 'Pending Items',
				'singular_name'      => 'Pending Item',
				'menu_name'          => 'Pending Items',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New Pending Item',
				'edit_item'          => 'Edit Pending Item',
				'new_item'           => 'New Pending Item',
				'view_item'          => 'View Pending Item',
				'search_items'       => 'Search Pending Items',
				'not_found'          => 'No pending items found',
				'not_found_in_trash' => 'No pending items found in Trash',
			],
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title' ],
		];

		register_post_type( 'ef_pending_item', $args );
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'ef-pending-item-details',
			'Pending Item Details',
			[ self::class, 'render_meta_box' ],
			'ef_pending_item',
			'normal',
			'high'
		);

		add_meta_box(
			'ef-pending-item-generate',
			'Generate Content',
			[ self::class, 'render_generate_meta_box' ],
			'ef_pending_item',
			'side',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		$type = get_post_meta( $post->ID, 'ef_item_type', true );
		$date = get_post_meta( $post->ID, 'ef_holiday_date', true );
		$country = get_post_meta( $post->ID, 'ef_country_name', true );
		$source = get_post_meta( $post->ID, '_ef_source_url', true );

		echo '<p><strong>Type:</strong> ' . esc_html( (string) $type ) . '</p>';
		if ( $type === 'holiday' ) {
			echo '<p><strong>Extracted Date:</strong> ' . esc_html( (string) $date ) . '</p>';
		} elseif ( $type === 'dish' ) {
			echo '<p><strong>Country:</strong> ' . esc_html( (string) $country ) . '</p>';
		}
		echo '<p><strong>Source URL:</strong> <a href="' . esc_url( (string) $source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $source ) . '</a></p>';
	}

	public static function render_generate_meta_box( \WP_Post $post ): void {
		if ( $post->post_status !== 'publish' ) {
			echo '<p>' . esc_html__( 'This item is not in the queue (not published).', 'eatforeign-api' ) . '</p>';
			return;
		}

		$ai_key = (string) get_option( 'eatforeign_ai_api_key', '' );
		if ( $ai_key === '' ) {
			echo '<p class="description">' . esc_html__( 'Set your AI API key under Settings → EatForeign API before generating.', 'eatforeign-api' ) . '</p>';
		}

		printf(
			'<p><button type="button" class="button button-primary button-large ef-generate-pending-item" data-pending-id="%1$d">%2$s</button></p>',
			(int) $post->ID,
			esc_html__( 'Generate & draft dish', 'eatforeign-api' )
		);
		echo '<p class="description">' . esc_html__( 'Runs AI content generation, creates a draft dish (and related celebration/country), then trashes this pending item.', 'eatforeign-api' ) . '</p>';
		printf(
			'<p id="ef-pending-generate-status-%1$d" class="ef-pending-generate-status" aria-live="polite"></p>',
			(int) $post->ID
		);
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== 'ef_pending_item' || $post->post_status !== 'publish' ) {
			return $actions;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$actions['ef_generate_draft'] = sprintf(
			'<a href="#" class="ef-generate-pending-item" data-pending-id="%1$d">%2$s</a>',
			(int) $post->ID,
			esc_html__( 'Generate draft', 'eatforeign-api' )
		);

		return $actions;
	}

	public static function enqueue_admin_scripts( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if (
			! $screen
			|| $screen->post_type !== 'ef_pending_item'
			|| ! in_array( $hook, [ 'edit.php', 'post.php', 'post-new.php' ], true )
		) {
			return;
		}

		$script_path = EATFOREIGN_API_DIR . 'assets/admin-pending-item.js';
		$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : EATFOREIGN_API_VERSION;

		wp_enqueue_script(
			'eatforeign-pending-item',
			plugins_url( 'assets/admin-pending-item.js', EATFOREIGN_API_FILE ),
			[],
			$version,
			true
		);

		wp_localize_script(
			'eatforeign-pending-item',
			'efPendingItem',
			[
				'nonce'           => wp_create_nonce( 'eatforeign_generate_pending_item' ),
				'reloadOnSuccess' => $hook === 'edit.php',
				'redirectUrl'     => $hook === 'post.php' ? admin_url( 'edit.php?post_type=ef_pending_item' ) : '',
				'i18n'            => [
					'generating'   => __( 'Generating draft dish… (AI may take a minute)', 'eatforeign-api' ),
					'success'      => __( 'Done.', 'eatforeign-api' ),
					'failed'       => __( 'Generation failed.', 'eatforeign-api' ),
					'networkError' => __( 'Network error.', 'eatforeign-api' ),
					'editDish'     => __( 'Edit dish', 'eatforeign-api' ),
				],
			]
		);

		wp_register_style( 'eatforeign-pending-item', false );
		wp_enqueue_style( 'eatforeign-pending-item' );
		wp_add_inline_style(
			'eatforeign-pending-item',
			'.ef-pending-generate-status{margin:.5em 0 0;}.ef-pending-generate-status--success{color:#007017;}.ef-pending-generate-status--error{color:#b32d2e;}.ef-pending-generate-status--info{color:#646970;}'
		);
	}

	public static function set_custom_columns( array $columns ): array {
		$columns['ef_item_type'] = 'Type';
		$columns['ef_details'] = 'Details';
		$columns['ef_source_url'] = 'Source URL';
		return $columns;
	}

	public static function render_custom_columns( string $column, int $post_id ): void {
		$type = get_post_meta( $post_id, 'ef_item_type', true );
		switch ( $column ) {
			case 'ef_item_type':
				echo esc_html( ucfirst( (string) $type ) );
				break;
			case 'ef_details':
				if ( $type === 'holiday' ) {
					echo 'Date: ' . esc_html( (string) get_post_meta( $post_id, 'ef_holiday_date', true ) );
				} elseif ( $type === 'dish' ) {
					echo 'Country: ' . esc_html( (string) get_post_meta( $post_id, 'ef_country_name', true ) );
				}
				break;
			case 'ef_source_url':
				$url = (string) get_post_meta( $post_id, '_ef_source_url', true );
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
				break;
		}
	}
}
