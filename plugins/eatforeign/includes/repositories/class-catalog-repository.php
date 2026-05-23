<?php
/**
 * Catalog queries.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Repositories;

use EatForeign\Support\PostType;
use WP_Post;
use WP_Query;

final class CatalogRepository {
	public static function get_by_slug( string $post_type, string $slug ): ?WP_Post {
		$post = get_page_by_path( $slug, OBJECT, $post_type );

		if (! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
			return null;
		}

		return $post;
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function get_today_celebrations( ?string $date = null ): array {
		$date  = $date ?? current_time( 'Y-m-d' );
		$parts = self::parse_event_date_parts( $date );

		if ( $parts === null ) {
			return [];
		}

		// Match month-day only so annual celebrations appear every year.
		$month_day = sprintf( '-%02d-%02d', $parts['month'], $parts['day'] );

		return self::query_posts(
			PostType::CELEBRATION,
			[
				'posts_per_page' => 200,
				'meta_query'     => [
					[
						'key'     => 'ef_event_date',
						'value'   => $month_day,
						'compare' => 'LIKE',
					],
				],
			]
		);
	}

	/**
	 * @return array<string, list<WP_Post>>
	 */
	public static function get_celebrations_for_month( int $year, int $month ): array {
		$month = min( 12, max( 1, $month ) );

		// Match any year for this month (e.g. %-05-% for May).
		$posts = self::query_posts(
			PostType::CELEBRATION,
			[
				'posts_per_page' => 500,
				'meta_query'     => [
					[
						'key'     => 'ef_event_date',
						'value'   => sprintf( '-%02d-', $month ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		$grouped = [];

		foreach ( $posts as $post ) {
			$stored = (string) get_post_meta( $post->ID, 'ef_event_date', true );
			$parts  = self::parse_event_date_parts( $stored );

			if ( $parts === null || $parts['month'] !== $month ) {
				continue;
			}

			// Group under the calendar year being viewed, not the stored year.
			$key = sprintf( '%04d-%02d-%02d', $year, $parts['month'], $parts['day'] );
			$grouped[ $key ]   = $grouped[ $key ] ?? [];
			$grouped[ $key ][] = $post;
		}

		ksort( $grouped );

		return $grouped;
	}

	/**
	 * Flag emoji for a celebration from its country term or linked featured dish.
	 */
	public static function get_celebration_flag_emoji( int $celebration_id ): string {
		$flag = self::get_flag_from_post_country_terms( $celebration_id );
		if ( $flag !== '' ) {
			return $flag;
		}

		$dish_ids = get_post_meta( $celebration_id, 'ef_featured_dish_ids', true );
		if ( ! is_array( $dish_ids ) || $dish_ids === [] ) {
			return '';
		}

		$dish_id = (int) $dish_ids[0];
		if ( $dish_id <= 0 ) {
			return '';
		}

		$flag = self::get_flag_from_post_country_terms( $dish_id );
		if ( $flag !== '' ) {
			return $flag;
		}

		return self::get_flag_by_country_name( (string) get_post_meta( $dish_id, 'ef_origin_country', true ) );
	}

	private static function get_flag_from_post_country_terms( int $post_id ): string {
		$terms = wp_get_post_terms( $post_id, 'ef_country', [ 'number' => 1 ] );

		if ( is_wp_error( $terms ) || $terms === [] ) {
			return '';
		}

		$flag = get_term_meta( $terms[0]->term_id, 'ef_flag_emoji', true );

		return is_string( $flag ) ? trim( $flag ) : '';
	}

	private static function get_flag_by_country_name( string $country_name ): string {
		$country_name = trim( $country_name );
		if ( $country_name === '' ) {
			return '';
		}

		$term = get_term_by( 'name', $country_name, 'ef_country' );
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$flag = get_term_meta( $term->term_id, 'ef_flag_emoji', true );

		return is_string( $flag ) ? trim( $flag ) : '';
	}

	/**
	 * @return array{year: int, month: int, day: int}|null
	 */
	private static function parse_event_date_parts( string $date ): ?array {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) !== 1 ) {
			return null;
		}

		return [
			'year'  => (int) $matches[1],
			'month' => (int) $matches[2],
			'day'   => (int) $matches[3],
		];
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function get_upcoming_celebrations( int $limit = 6 ): array {
		$today = current_time( 'Y-m-d' );

		return self::query_posts(
			PostType::CELEBRATION,
			[
				'posts_per_page' => $limit,
				'meta_key'       => 'ef_event_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => [
					[
						'key'     => 'ef_event_date',
						'value'   => $today,
						'compare' => '>',
						'type'    => 'DATE',
					],
				],
			]
		);
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function filter_directory_dishes( array $filters ): array {
		$result = self::filter_directory_dishes_paginated( $filters, 1, 100 );

		return $result['posts'];
	}

	/**
	 * @return array{posts: list<WP_Post>, total: int, max_pages: int, current_page: int, per_page: int}
	 */
	public static function filter_directory_dishes_paginated( array $filters, int $page = 1, int $per_page = 12 ): array {
		$args = [
			'paged'          => max( 1, $page ),
			'posts_per_page' => max( 1, $per_page ),
			's'              => isset( $filters['query'] ) ? sanitize_text_field( (string) $filters['query'] ) : '',
		];

		$tax_query = [];

		if (! empty( $filters['cuisine'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'ef_cuisine',
				'field'    => 'name',
				'terms'    => sanitize_text_field( (string) $filters['cuisine'] ),
			];
		}

		if (! empty( $filters['countrySlug'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'ef_country',
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $filters['countrySlug'] ),
			];
		}

		if (! empty( $filters['dishType'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'ef_dish_type',
				'field'    => 'name',
				'terms'    => sanitize_text_field( (string) $filters['dishType'] ),
			];
		}

		if ( $tax_query !== [] ) {
			$args['tax_query'] = $tax_query;
		}

		return self::query_posts_paginated( PostType::DISH, $args );
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function get_related_posts( int $post_id, string $meta_key ): array {
		return self::query_posts(
			PostType::DISH,
			[
				'posts_per_page' => 50,
				'meta_query'     => [
					[
						'key'     => $meta_key,
						'value'   => '"' . $post_id . '"',
						'compare' => 'LIKE',
					],
				],
			]
		);
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function query_posts( string $post_type, array $args = [] ): array {
		$result = self::query_posts_paginated( $post_type, $args );

		return $result['posts'];
	}

	/**
	 * @return array{posts: list<WP_Post>, total: int, max_pages: int, current_page: int, per_page: int}
	 */
	public static function query_posts_paginated( string $post_type, array $args = [] ): array {
		$page     = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$per_page = (int) ( $args['posts_per_page'] ?? 20 );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		unset( $args['paged'] );

		$query = new WP_Query(
			array_merge(
				[
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'orderby'        => 'title',
					'order'          => 'ASC',
				],
				$args
			)
		);

		return [
			'posts'        => array_values( $query->posts ),
			'total'        => (int) $query->found_posts,
			'max_pages'    => (int) $query->max_num_pages,
			'current_page' => $page,
			'per_page'     => $per_page,
		];
	}

	public static function format_post( WP_Post $post ): array {
		$thumbnail = get_the_post_thumbnail_url( $post, 'large' );

		return [
			'id'    => $post->ID,
			'title' => get_the_title( $post ),
			'slug'  => $post->post_name,
			'link'  => get_permalink( $post ),
			'meta'  => get_post_meta( $post->ID ),
			'image' => $thumbnail ?: '',
		];
	}
}
