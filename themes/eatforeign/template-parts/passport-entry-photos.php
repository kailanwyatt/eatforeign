<?php
/**
 * Thumbnail row for passport entry photos.
 *
 * @package EatForeignTheme
 *
 * @var list<array{url?: string, caption?: string}> $photos
 */

declare(strict_types=1);

$photos = isset( $args['photos'] ) && is_array( $args['photos'] ) ? $args['photos'] : [];

if ( $photos === [] ) {
	return;
}
?>
<ul class="ef-passport-photo-list" aria-label="<?php esc_attr_e( 'Passport photos', 'eatforeign' ); ?>">
	<?php foreach ( $photos as $photo ) : ?>
		<?php
		$url     = isset( $photo['url'] ) ? (string) $photo['url'] : '';
		$caption = isset( $photo['caption'] ) ? (string) $photo['caption'] : '';

		if ( $url === '' ) {
			continue;
		}
		?>
		<li class="ef-passport-photo-list__item">
			<img class="ef-passport-photo-list__image" src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy" />
			<?php if ( $caption !== '' ) : ?>
				<p class="ef-passport-photo-list__caption"><?php echo esc_html( $caption ); ?></p>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
