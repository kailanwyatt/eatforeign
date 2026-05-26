<?php
/**
 * Country card.
 *
 * @package EatForeignTheme
 *
 * @var array{post?: WP_Post} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;

$post = $args['post'] ?? null;

if (! $post instanceof WP_Post ) {
	return;
}

$card_image     = Data::country_card_image( $post );
$image          = (string) ( $card_image['url'] ?? '' );
$is_placeholder = (bool) ( $card_image['is_placeholder'] ?? true );
$image_alt      = (string) ( $card_image['alt'] ?? '' );
$featured_dish  = isset( $card_image['dish'] ) && $card_image['dish'] instanceof WP_Post ? $card_image['dish'] : null;
$overview       = (string) get_post_meta( $post->ID, 'ef_overview', true );
$excerpt   = get_the_excerpt( $post );
$copy      = Data::has_text( $overview ) ? $overview : $excerpt;
$flag      = Data::country_flag( $post );
$name      = Data::country_display_name( $post );

?>
<article class="ef-card ef-card--country">
	<a class="ef-card__link" href="<?php echo esc_url( Data::catalog_permalink( $post ) ); ?>">
		<div class="ef-card__media">
			<img
				class="ef-card__image<?php echo $is_placeholder ? ' ef-card__image--placeholder' : ''; ?>"
				src="<?php echo esc_url( $image ); ?>"
				alt="<?php echo esc_attr( $image_alt !== '' ? $image_alt : $name ); ?>"
				loading="lazy"
			/>
			<?php if ( $featured_dish instanceof WP_Post ) : ?>
				<span class="ef-card__media-label"><?php echo esc_html( get_the_title( $featured_dish ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="ef-card__body">
			<div class="ef-card__title-row ef-card__title-row--country">
				<?php if ( $flag !== '' ) : ?>
					<span class="ef-card__country-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span>
				<?php endif; ?>
				<h3 class="ef-card__title"><?php echo esc_html( $name ); ?></h3>
			</div>
			<?php if ( Data::has_text( $copy ) ) : ?>
				<p class="ef-card__copy"><?php echo esc_html( $copy ); ?></p>
			<?php endif; ?>
		</div>
	</a>
</article>
