<?php
/**
 * Dish card.
 *
 * @package EatForeignTheme
 *
 * @var array{post?: WP_Post, layout?: string} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;

$post = $args['post'] ?? null;

if (! $post instanceof WP_Post ) {
	return;
}

$layout        = isset( $args['layout'] ) ? (string) $args['layout'] : 'default';
$image         = Data::post_display_image( $post );
$is_placeholder = Data::post_uses_placeholder_image( $post );
$origin        = (string) get_post_meta( $post->ID, 'ef_origin_country', true );
$rating        = (float) get_post_meta( $post->ID, 'ef_average_rating', true );
$description   = get_the_excerpt( $post );
$cuisine_terms = wp_get_post_terms( $post->ID, 'ef_cuisine', [ 'fields' => 'names' ] );
$cuisine       = is_array( $cuisine_terms ) && isset( $cuisine_terms[0] ) ? (string) $cuisine_terms[0] : '';
$dish_types    = wp_get_post_terms( $post->ID, 'ef_dish_type', [ 'fields' => 'names' ] );
$dish_type     = is_array( $dish_types ) && isset( $dish_types[0] ) ? (string) $dish_types[0] : '';
$eat_yes       = (int) get_post_meta( $post->ID, 'ef_eat_yes_count', true );
$eat_total     = (int) get_post_meta( $post->ID, 'ef_eat_total_count', true );
$eat_pct       = $eat_total > 0 ? (int) round( 100 * $eat_yes / $eat_total ) : 0;

$country_terms = wp_get_post_terms( $post->ID, 'ef_country', [ 'fields' => 'names' ] );
$country       = is_array( $country_terms ) && isset( $country_terms[0] ) ? (string) $country_terms[0] : ( $origin !== '' ? $origin : '' );

$is_directory = $layout === 'directory';
$card_class   = $is_directory ? 'ef-card ef-card--dish ef-card--dish-directory' : 'ef-card ef-card--dish';

?>
<article class="<?php echo esc_attr( $card_class ); ?>">
	<a class="ef-card__link" href="<?php echo esc_url( Data::catalog_permalink( $post ) ); ?>">
		<div class="ef-card__media">
			<img
				class="ef-card__image<?php echo $is_placeholder ? ' ef-card__image--placeholder' : ''; ?>"
				src="<?php echo esc_url( $image ); ?>"
				alt="<?php echo esc_attr( get_the_title( $post ) ); ?>"
				loading="lazy"
			/>
			<?php if ( $is_directory && $country !== '' ) : ?>
				<span class="ef-card__flag"><?php echo esc_html( $country ); ?></span>
			<?php endif; ?>
		</div>
		<div class="ef-card__body">
			<?php if (! $is_directory && $cuisine !== '' ) : ?>
				<p class="ef-card__meta"><?php echo esc_html( $cuisine ); ?></p>
			<?php elseif (! $is_directory && $origin !== '' ) : ?>
				<p class="ef-card__meta"><?php echo esc_html( $origin ); ?></p>
			<?php endif; ?>
			<div class="ef-card__title-row">
				<h3 class="ef-card__title"><?php echo esc_html( get_the_title( $post ) ); ?></h3>
				<?php if ( $rating > 0 ) : ?>
					<div class="ef-card__stars" aria-hidden="true">
						<?php echo str_repeat( '★', (int) round( min( 5, $rating ) ) ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( Data::has_text( $description ) ) : ?>
				<p class="ef-card__copy"><?php echo esc_html( wp_trim_words( $description, 22 ) ); ?></p>
			<?php endif; ?>
			<?php if ( $is_directory ) : ?>
				<ul class="ef-tag-list ef-tag-list--compact">
					<?php if ( $dish_type !== '' ) : ?>
						<li><?php echo esc_html( $dish_type ); ?></li>
					<?php endif; ?>
					<?php if ( $cuisine !== '' ) : ?>
						<li><?php echo esc_html( $cuisine ); ?></li>
					<?php endif; ?>
				</ul>
				<div class="ef-eat-poll">
					<p class="ef-eat-poll__label"><?php esc_html_e( 'Would you eat this?', 'eatforeign' ); ?></p>
					<p class="ef-eat-poll__dish"><?php echo esc_html( get_the_title( $post ) ); ?></p>
					<div class="ef-eat-poll__actions">
						<span class="ef-eat-pill"><?php esc_html_e( 'Yes', 'eatforeign' ); ?></span>
						<span class="ef-eat-pill"><?php esc_html_e( 'Not yet', 'eatforeign' ); ?></span>
					</div>
					<div class="ef-eat-poll__bar" role="presentation">
						<span class="ef-eat-poll__fill" style="width: <?php echo esc_attr( (string) max( 8, $eat_pct ) ); ?>%;"></span>
					</div>
					<p class="ef-eat-poll__meta">
						<?php
						if ( $eat_total > 0 ) {
							echo esc_html(
								sprintf(
									/* translators: 1: yes count, 2: total votes */
									__( '%1$s yes from %2$s votes', 'eatforeign' ),
									number_format_i18n( $eat_yes ),
									number_format_i18n( $eat_total )
								)
							);
						} else {
							esc_html_e( 'Be the first to vote on this dish.', 'eatforeign' );
						}
						?>
					</p>
				</div>
			<?php elseif ( $rating > 0 ) : ?>
				<p class="ef-card__rating"><?php echo esc_html( number_format( $rating, 1 ) ); ?> <?php esc_html_e( 'average', 'eatforeign' ); ?></p>
			<?php endif; ?>
		</div>
	</a>
</article>
