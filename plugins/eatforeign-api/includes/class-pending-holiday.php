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
	}

	public static function render_meta_box( \WP_Post $post ): void {
		$type = get_post_meta( $post->ID, 'ef_item_type', true );
		$date = get_post_meta( $post->ID, 'ef_holiday_date', true );
		$country = get_post_meta( $post->ID, 'ef_country_name', true );
		$source = get_post_meta( $post->ID, '_ef_source_url', true );
		
		echo '<p><strong>Type:</strong> ' . esc_html( (string) $type ) . '</p>';
		if ( $type === 'holiday' ) {
			echo '<p><strong>Extracted Date:</strong> ' . esc_html( (string) $date ) . '</p>';
		} else if ( $type === 'dish' ) {
			echo '<p><strong>Country:</strong> ' . esc_html( (string) $country ) . '</p>';
		}
		echo '<p><strong>Source URL:</strong> <a href="' . esc_url( (string) $source ) . '" target="_blank">' . esc_html( (string) $source ) . '</a></p>';
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
				} else if ( $type === 'dish' ) {
					echo 'Country: ' . esc_html( (string) get_post_meta( $post_id, 'ef_country_name', true ) );
				}
				break;
			case 'ef_source_url':
				$url = (string) get_post_meta( $post_id, '_ef_source_url', true );
				echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>';
				break;
		}
	}
}
