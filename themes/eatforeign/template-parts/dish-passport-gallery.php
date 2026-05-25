<?php
/**
 * Community passport photos for a dish.
 *
 * @package EatForeignTheme
 *
 * @var list<array<string, mixed>> $photos
 */

declare(strict_types=1);

$photos = isset( $args['photos'] ) && is_array( $args['photos'] ) ? $args['photos'] : [];

if ( $photos === [] ) {
	return;
}
?>
<div class="ef-dish-passport-gallery">
	<?php foreach ( $photos as $photo ) : ?>
		<?php
		$url         = (string) ( $photo['url'] ?? '' );
		$caption     = (string) ( $photo['caption'] ?? '' );
		$author      = (string) ( $photo['authorDisplayName'] ?? '' );
		$author_slug = (string) ( $photo['authorSlug'] ?? '' );
		$passport    = (string) ( $photo['authorPassportUrl'] ?? '' );
		$rating      = (float) ( $photo['rating'] ?? 0 );

		if ( $url === '' ) {
			continue;
		}
		?>
		<figure class="ef-dish-passport-gallery__card">
			<img class="ef-dish-passport-gallery__image" src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy" />
			<figcaption class="ef-dish-passport-gallery__body">
				<?php if ( $caption !== '' ) : ?>
					<p class="ef-dish-passport-gallery__caption"><?php echo esc_html( $caption ); ?></p>
				<?php endif; ?>
				<p class="ef-dish-passport-gallery__meta">
					<?php if ( $author !== '' && $passport !== '' ) : ?>
						<a href="<?php echo esc_url( $passport ); ?>"><?php echo esc_html( $author ); ?></a>
					<?php elseif ( $author !== '' ) : ?>
						<?php echo esc_html( $author ); ?>
					<?php endif; ?>
					<?php if ( $rating > 0 ) : ?>
						<span class="ef-dish-passport-gallery__rating"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
					<?php endif; ?>
				</p>
			</figcaption>
		</figure>
	<?php endforeach; ?>
</div>
