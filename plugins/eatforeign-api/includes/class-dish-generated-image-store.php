<?php
/**
 * Persist AI-generated dish image URLs and attribution on a dish post.
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class DishGeneratedImageStore {
	public static function save( int $dish_id, string $image_url ): void {
		$image_url = esc_url_raw( $image_url );
		if ( $image_url === '' ) {
			return;
		}

		$existing = get_post_meta( $dish_id, 'ef_ai_generated_images', true );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing[] = $image_url;
		update_post_meta( $dish_id, 'ef_ai_generated_images', array_values( array_unique( $existing ) ) );

		$ai_source = ImageAttribution::ai_record( $image_url );
		$sources   = get_post_meta( $dish_id, 'ef_suggested_image_sources', true );
		if ( ! is_array( $sources ) ) {
			$sources = [];
		}
		$sources[] = $ai_source;
		$by_url = [];
		foreach ( $sources as $source ) {
			if ( is_array( $source ) && ! empty( $source['url'] ) ) {
				$by_url[ (string) $source['url'] ] = $source;
			}
		}
		update_post_meta( $dish_id, 'ef_suggested_image_sources', array_values( $by_url ) );
	}
}
