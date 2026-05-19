<?php
/**
 * Theme data access for EatForeign catalog content.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

use EatForeign\Repositories\CatalogRepository;
use EatForeign\Repositories\CommunityRepository;
use EatForeign\Repositories\PassportRepository;
use EatForeign\Support\PostType;
use WP_Post;
use WP_Query;
use WP_Term;

final class Data {
	public static function plugin_ready(): bool {
		return class_exists( CatalogRepository::class );
	}

	/**
	 * @return array<string, list<WP_Post>>
	 */
	public static function celebrations_grouped_for_month( int $year, int $month ): array {
		return self::plugin_ready() ? CatalogRepository::get_celebrations_for_month( $year, $month ) : [];
	}

	public static function celebration_flag_emoji( WP_Post $post ): string {
		return self::plugin_ready() ? CatalogRepository::get_celebration_flag_emoji( $post->ID ) : '';
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function directory_dishes(): array {
		if (! self::plugin_ready() ) {
			return [];
		}

		return CatalogRepository::filter_directory_dishes(
			[
				'query'        => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '',
				'cuisine'      => isset( $_GET['cuisine'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['cuisine'] ) ) : '',
				'countrySlug'  => isset( $_GET['country'] ) ? sanitize_title( wp_unslash( (string) $_GET['country'] ) ) : '',
				'dishType'     => isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['type'] ) ) : '',
			]
		);
	}

	/**
	 * @return list<WP_Term>
	 */
	public static function terms_for_taxonomy( string $taxonomy, int $limit = 40 ): array {
		if (! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $limit,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		return is_array( $terms ) ? array_values( array_filter( $terms, static fn ( $t ): bool => $t instanceof WP_Term ) ) : [];
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function today_celebrations(): array {
		return self::plugin_ready() ? CatalogRepository::get_today_celebrations() : [];
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function trending_dishes( int $limit = 6 ): array {
		if (! self::plugin_ready() ) {
			return [];
		}

		return CatalogRepository::query_posts(
			PostType::DISH,
			[
				'posts_per_page' => $limit,
				'meta_key'       => 'ef_average_rating',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			]
		);
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function most_celebrated_today( int $limit = 4 ): array {
		$celebrations = self::today_celebrations();

		if ( $celebrations === [] || ! class_exists( CommunityRepository::class ) ) {
			return [];
		}

		usort(
			$celebrations,
			static function ( WP_Post $left, WP_Post $right ): int {
				$left_count  = count( CommunityRepository::get_posts_for_celebration( $left->ID ) );
				$right_count = count( CommunityRepository::get_posts_for_celebration( $right->ID ) );

				return $right_count <=> $left_count;
			}
		);

		return array_values(
			array_filter(
				$celebrations,
				static fn ( WP_Post $celebration ): bool => count( CommunityRepository::get_posts_for_celebration( $celebration->ID ) ) > 0
			)
		) !== [] ? array_slice( $celebrations, 0, $limit ) : [];
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function countries( int $limit = 6 ): array {
		if (! self::plugin_ready() ) {
			return [];
		}

		return CatalogRepository::query_posts(
			PostType::COUNTRY,
			[
				'posts_per_page' => $limit,
			]
		);
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function upcoming_celebrations( int $limit = 6 ): array {
		return self::plugin_ready() ? CatalogRepository::get_upcoming_celebrations( $limit ) : [];
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function featured_posts( int $limit = 6 ): array {
		if (! self::plugin_ready() || ! class_exists( CommunityRepository::class ) ) {
			return [];
		}

		return CommunityRepository::get_featured_posts( $limit );
	}

	/**
	 * @return list<WP_Post>
	 */
	public static function posts_by_ids( array $post_ids ): array {
		$post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );

		if ( $post_ids === [] || ! self::plugin_ready() ) {
			return [];
		}

		$posts = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof WP_Post && $post->post_status === 'publish' ) {
				$posts[] = $post;
			}
		}

		return $posts;
	}

	public static function has_text( mixed $value ): bool {
		return is_string( $value ) && trim( $value ) !== '';
	}

	public static function post_image( WP_Post $post ): string {
		$thumbnail = get_the_post_thumbnail_url( $post, 'large' );

		if ( is_string( $thumbnail ) && $thumbnail !== '' ) {
			return $thumbnail;
		}

		$meta_image = get_post_meta( $post->ID, 'ef_hero_image_url', true );

		if ( is_string( $meta_image ) && $meta_image !== '' ) {
			return $meta_image;
		}

		$gallery = get_post_meta( $post->ID, 'ef_gallery_urls', true );

		if ( is_array( $gallery ) && isset( $gallery[0] ) && is_string( $gallery[0] ) && $gallery[0] !== '' ) {
			return $gallery[0];
		}

		$image_url = get_post_meta( $post->ID, 'ef_image_url', true );

		return is_string( $image_url ) ? $image_url : '';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function public_passports(): array {
		return self::plugin_ready() && class_exists( PassportRepository::class )
			? PassportRepository::get_public_passports()
			: [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function passport_by_slug( string $slug ): ?array {
		if (! self::plugin_ready() || ! class_exists( PassportRepository::class ) ) {
			return null;
		}

		return PassportRepository::get_by_slug( $slug, get_current_user_id() );
	}

	public static function user_rating_for_dish( int $dish_id ): float {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return 0.0;
		}

		$ratings = get_user_meta( $user_id, 'ef_dish_ratings', true );

		if (! is_array( $ratings ) || ! isset( $ratings[ $dish_id ] ) ) {
			return 0.0;
		}

		return (float) $ratings[ $dish_id ];
	}

	public static function celebration_completed( int $celebration_id ): bool {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		$completed = get_user_meta( $user_id, 'ef_completed_celebration_ids', true );

		return is_array( $completed ) && in_array( $celebration_id, array_map( 'absint', $completed ), true );
	}

	/**
	 * Countries linked to a dish: origin meta, taxonomy, national-dish lists, and linked celebrations.
	 *
	 * @return array{
	 *     primary: array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}|null,
	 *     all: list<array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}>
	 * }
	 */
	public static function dish_countries( int $dish_id ): array {
		$rows   = [];
		$origin = trim( (string) get_post_meta( $dish_id, 'ef_origin_country', true ) );

		if ( $origin !== '' ) {
			$rows[] = self::country_row_from_name( $origin, 'origin' );
		}

		$terms = wp_get_post_terms( $dish_id, 'ef_country' );

		if (! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term ) {
					$rows[] = self::country_row_from_term( $term, 'home' );
				}
			}
		}

		$country_posts = new WP_Query(
			[
				'post_type'      => PostType::COUNTRY,
				'post_status'    => 'publish',
				'posts_per_page' => 40,
				'meta_query'     => [
					[
						'key'     => 'ef_dish_ids',
						'value'   => '"' . $dish_id . '"',
						'compare' => 'LIKE',
					],
				],
				'no_found_rows'  => true,
			]
		);

		if ( $country_posts->have_posts() ) {
			while ( $country_posts->have_posts() ) {
				$country_posts->the_post();
				$post = get_post();

				if ( $post instanceof WP_Post ) {
					$rows[] = self::country_row_from_post( $post, 'popular' );
				}
			}

			wp_reset_postdata();
		}

		foreach ( self::posts_by_ids( (array) get_post_meta( $dish_id, 'ef_celebration_ids', true ) ) as $celebration ) {
			$celebration_terms = wp_get_post_terms( $celebration->ID, 'ef_country' );

			if ( is_wp_error( $celebration_terms ) ) {
				continue;
			}

			foreach ( $celebration_terms as $term ) {
				if ( $term instanceof WP_Term ) {
					$rows[] = self::country_row_from_term( $term, 'celebration' );
				}
			}
		}

		$all = self::merge_country_rows( $rows );
		$primary = null;

		foreach ( $all as $row ) {
			if ( $row['role'] === 'origin' ) {
				$primary = $row;
				break;
			}
		}

		if ( $primary === null && $all !== [] ) {
			$primary = $all[0];
		}

		return [
			'primary' => $primary,
			'all'     => $all,
		];
	}

	/**
	 * @param list<array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}> $rows
	 * @return list<array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}>
	 */
	private static function merge_country_rows( array $rows ): array {
		$merged   = [];
		$priority = [
			'origin'      => 4,
			'home'        => 3,
			'popular'     => 2,
			'celebration' => 1,
		];

		foreach ( $rows as $row ) {
			$key = $row['slug'] !== '' ? 'slug:' . $row['slug'] : 'name:' . strtolower( $row['name'] );

			if (! isset( $merged[ $key ] ) || ( $priority[ $row['role'] ] ?? 0 ) > ( $priority[ $merged[ $key ]['role'] ] ?? 0 ) ) {
				$merged[ $key ] = $row;
			} elseif ( $row['flag'] !== '' && $merged[ $key ]['flag'] === '' ) {
				$merged[ $key ]['flag'] = $row['flag'];
			} elseif ( $row['url'] !== '' && $merged[ $key ]['url'] === '' ) {
				$merged[ $key ]['url'] = $row['url'];
			}
		}

		usort(
			$merged,
			static function ( array $left, array $right ) use ( $priority ): int {
				$left_score  = $priority[ $left['role'] ] ?? 0;
				$right_score = $priority[ $right['role'] ] ?? 0;

				if ( $left_score !== $right_score ) {
					return $right_score <=> $left_score;
				}

				return strcasecmp( $left['name'], $right['name'] );
			}
		);

		return array_values( $merged );
	}

	/**
	 * @return array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}
	 */
	private static function country_row_from_name( string $name, string $role ): array {
		$term = get_term_by( 'name', $name, 'ef_country' );

		if ( $term instanceof WP_Term ) {
			return self::country_row_from_term( $term, $role );
		}

		$post = get_page_by_title( $name, OBJECT, PostType::COUNTRY );

		if ( $post instanceof WP_Post ) {
			return self::country_row_from_post( $post, $role );
		}

		return self::format_country_row( $name, sanitize_title( $name ), '', '', $role );
	}

	/**
	 * @return array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}
	 */
	private static function country_row_from_term( WP_Term $term, string $role ): array {
		$flag = (string) get_term_meta( $term->term_id, 'ef_flag_emoji', true );
		$url  = '';
		$post = get_page_by_title( $term->name, OBJECT, PostType::COUNTRY );

		if ( $post instanceof WP_Post ) {
			$url = (string) get_permalink( $post );

			if ( $flag === '' ) {
				$flag = self::flag_for_country_post( $post );
			}
		}

		return self::format_country_row( $term->name, $term->slug, $flag, $url, $role );
	}

	/**
	 * @return array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}
	 */
	private static function country_row_from_post( WP_Post $post, string $role ): array {
		$name = get_the_title( $post );
		$slug = $post->post_name;
		$flag = self::flag_for_country_post( $post );
		$term = get_term_by( 'name', $name, 'ef_country' );

		if ( $term instanceof WP_Term ) {
			if ( $flag === '' ) {
				$flag = (string) get_term_meta( $term->term_id, 'ef_flag_emoji', true );
			}

			if ( $slug === '' ) {
				$slug = $term->slug;
			}
		}

		return self::format_country_row( $name, $slug, $flag, (string) get_permalink( $post ), $role );
	}

	private static function flag_for_country_post( WP_Post $post ): string {
		$term = get_term_by( 'name', get_the_title( $post ), 'ef_country' );

		if ( $term instanceof WP_Term ) {
			return (string) get_term_meta( $term->term_id, 'ef_flag_emoji', true );
		}

		return '';
	}

	/**
	 * @return array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}
	 */
	private static function format_country_row(
		string $name,
		string $slug,
		string $flag,
		string $url,
		string $role
	): array {
		return [
			'name'       => $name,
			'slug'       => $slug,
			'flag'       => $flag,
			'url'        => $url,
			'role'       => $role,
			'role_label' => self::country_role_label( $role ),
		];
	}

	private static function country_role_label( string $role ): string {
		return match ( $role ) {
			'origin'      => __( 'Origin', 'eatforeign' ),
			'home'        => __( 'Home country', 'eatforeign' ),
			'popular'     => __( 'Popular here', 'eatforeign' ),
			'celebration' => __( 'Celebrated here', 'eatforeign' ),
			default       => __( 'Related', 'eatforeign' ),
		};
	}

	/**
	 * @return list<string>
	 */
	public static function post_term_names( int $post_id, string $taxonomy ): array {
		if (! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );

		return is_array( $terms ) ? array_values( array_map( 'strval', $terms ) ) : [];
	}
}
