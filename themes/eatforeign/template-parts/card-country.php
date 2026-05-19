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

$image     = Data::post_image( $post );
$overview  = (string) get_post_meta( $post->ID, 'ef_overview', true );
$excerpt   = get_the_excerpt( $post );
$copy      = Data::has_text( $overview ) ? $overview : $excerpt;

?>
<article class="ef-card">
	<a class="ef-card__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<?php if ( $image !== '' ) : ?>
			<img class="ef-card__image" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy" />
		<?php endif; ?>
		<div class="ef-card__body">
			<h3 class="ef-card__title"><?php echo esc_html( get_the_title( $post ) ); ?></h3>
			<?php if ( Data::has_text( $copy ) ) : ?>
				<p class="ef-card__copy"><?php echo esc_html( $copy ); ?></p>
			<?php endif; ?>
		</div>
	</a>
</article>
