<?php
/**
 * Community post card.
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

$image          = Data::post_display_image( $post );
$is_placeholder = Data::post_uses_placeholder_image( $post );
$caption = (string) get_post_meta( $post->ID, 'ef_caption', true );
$rating  = (float) get_post_meta( $post->ID, 'ef_rating', true );
$copy    = Data::has_text( $caption ) ? $caption : get_the_excerpt( $post );

?>
<article class="ef-card ef-card--post">
	<div class="ef-card__media">
		<img
			class="ef-card__image<?php echo $is_placeholder ? ' ef-card__image--placeholder' : ''; ?>"
			src="<?php echo esc_url( $image ); ?>"
			alt=""
			loading="lazy"
		/>
	</div>
	<div class="ef-card__body">
		<h3 class="ef-card__title"><?php echo esc_html( get_the_title( $post ) ); ?></h3>
		<?php if ( Data::has_text( $copy ) ) : ?>
			<p class="ef-card__copy"><?php echo esc_html( $copy ); ?></p>
		<?php endif; ?>
		<?php if ( $rating > 0 ) : ?>
			<p class="ef-card__rating"><?php echo esc_html( number_format( $rating, 1 ) ); ?> rating</p>
		<?php endif; ?>
	</div>
</article>
