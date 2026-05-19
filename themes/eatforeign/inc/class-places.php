<?php
/**
 * Nearby restaurant discovery for theme templates.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

final class Places {
	/**
	 * @return list<array<string, mixed>>
	 */
	public static function nearby_for_dish( string $dish_title, ?string $location = null ): array {
		if ( ! class_exists( '\EatForeignAPI\PlacesClient' ) ) {
			return [];
		}

		$location = $location ?? self::default_location();

		if ( $dish_title === '' || $location === '' ) {
			return [];
		}

		return \EatForeignAPI\PlacesClient::get_restaurants( $dish_title, $location );
	}

	public static function default_location(): string {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			$label = (string) get_user_meta( $user_id, 'ef_preferred_location_label', true );
			if ( $label !== '' ) {
				return $label;
			}
		}

		return 'United States';
	}

	/**
	 * @param list<array<string, mixed>> $restaurants
	 */
	public static function render_list( array $restaurants ): void {
		if ( $restaurants === [] ) {
			echo '<p>' . esc_html__( 'No nearby restaurants found. Try updating your location in your account settings.', 'eatforeign' ) . '</p>';
			return;
		}

		echo '<ul class="ef-restaurant-list">';

		foreach ( $restaurants as $restaurant ) {
			$name    = (string) ( $restaurant['name'] ?? '' );
			$address = (string) ( $restaurant['address'] ?? '' );
			$website = (string) ( $restaurant['website'] ?? '' );
			$rating  = (float) ( $restaurant['rating'] ?? 0 );

			echo '<li class="ef-restaurant-list__item">';
			echo '<strong>' . esc_html( $name ) . '</strong>';

			if ( $rating > 0 ) {
				echo ' <span class="ef-restaurant-list__rating">' . esc_html( number_format( $rating, 1 ) ) . '★</span>';
			}

			if ( $address !== '' ) {
				echo '<p class="ef-restaurant-list__address">' . esc_html( $address ) . '</p>';
			}

			if ( $website !== '' ) {
				echo '<p><a href="' . esc_url( $website ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'eatforeign' ) . '</a></p>';
			}

			echo '</li>';
		}

		echo '</ul>';
	}
}
