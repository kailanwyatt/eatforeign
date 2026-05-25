<?php
/**
 * Success recap: photo collage, rating, and comment.
 *
 * @package EatForeignTheme
 *
 * @var WP_Post $dish
 * @var array<string, mixed> $entry
 * @var string $hero_image
 */

declare(strict_types=1);

use EatForeignTheme\Data;

if ( ! isset( $dish ) || ! $dish instanceof WP_Post ) {
	return;
}

$entry = is_array( $entry ) ? $entry : [];
$photos = is_array( $entry['photos'] ?? null ) ? $entry['photos'] : [];
$display = [];

foreach ( $photos as $photo ) {
	$url = isset( $photo['url'] ) ? (string) $photo['url'] : '';

	if ( $url === '' ) {
		continue;
	}

	$display[] = [
		'url'     => $url,
		'caption' => isset( $photo['caption'] ) ? (string) $photo['caption'] : '',
	];
}

$is_fallback = false;

if ( $display === [] ) {
	$fallback_url = isset( $hero_image ) ? (string) $hero_image : Data::post_image( $dish );

	if ( $fallback_url !== '' ) {
		$display[]   = [
			'url'     => $fallback_url,
			'caption' => '',
		];
		$is_fallback = true;
	}
}

if ( $display === [] ) {
	return;
}

$rating       = (float) ( $entry['rating'] ?? 0 );
$note         = isset( $entry['note'] ) ? trim( (string) $entry['note'] ) : '';
$restaurant   = isset( $entry['restaurantName'] ) ? trim( (string) $entry['restaurantName'] ) : '';
$first_time   = ! empty( $entry['firstTimeTrying'] );
$photo_count  = min( 6, count( $display ) );
$collage_mod  = 'ef-passport-collage--count-' . $photo_count;

if ( $photo_count >= 5 ) {
	$collage_mod = 'ef-passport-collage--count-many';
}

$faces = [
	1 => '😕',
	2 => '🙂',
	3 => '😊',
	4 => '🤩',
	5 => '🔥',
];
$rating_face = '';

if ( $rating > 0 ) {
	$bucket      = (int) max( 1, min( 5, round( $rating ) ) );
	$rating_face = $faces[ $bucket ] ?? '🙂';
}
?>
<div class="ef-passport-stamp-recap">
	<div class="ef-passport-stamp-recap__visual">
		<div class="ef-passport-collage <?php echo esc_attr( $collage_mod ); ?>" data-photo-count="<?php echo esc_attr( (string) $photo_count ); ?>">
			<?php foreach ( array_slice( $display, 0, 6 ) as $index => $photo ) : ?>
				<figure class="ef-passport-collage__cell">
					<img src="<?php echo esc_url( $photo['url'] ); ?>" alt="" loading="lazy" />
					<?php if ( $photo['caption'] !== '' ) : ?>
						<figcaption class="ef-passport-collage__caption"><?php echo esc_html( $photo['caption'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
		<span class="ef-passport-stamp-recap__badge" aria-hidden="true">
			<svg viewBox="0 0 48 48" width="48" height="48" aria-hidden="true">
				<circle cx="24" cy="24" r="24" fill="#c45c26"/>
				<path d="M14 24 L21 31 L34 18" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</span>
	</div>

	<?php if ( $is_fallback ) : ?>
		<p class="ef-passport-stamp-recap__fallback"><?php esc_html_e( 'No photo yet — showing the dish for now. You can add yours anytime.', 'eatforeign' ); ?></p>
	<?php endif; ?>

	<div class="ef-passport-stamp-recap__meta">
		<p class="ef-passport-stamp-recap__dish"><?php echo esc_html( $dish->post_title ); ?></p>

		<?php if ( $rating > 0 ) : ?>
			<div class="ef-passport-stamp-recap__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: rating */ __( 'Your rating: %s out of 5', 'eatforeign' ), number_format( $rating, 1 ) ) ); ?>">
				<?php if ( $rating_face !== '' ) : ?>
					<span class="ef-passport-stamp-recap__face" aria-hidden="true"><?php echo esc_html( $rating_face ); ?></span>
				<?php endif; ?>
				<span class="ef-passport-stamp-recap__score"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
				<span class="ef-passport-stamp-recap__stars" aria-hidden="true">
					<?php
					for ( $i = 1; $i <= 5; $i++ ) {
						echo $i <= round( $rating ) ? '★' : '☆';
					}
					?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $note !== '' ) : ?>
			<blockquote class="ef-passport-stamp-recap__quote">“<?php echo esc_html( $note ); ?>”</blockquote>
		<?php endif; ?>

		<?php if ( $restaurant !== '' || $first_time ) : ?>
			<ul class="ef-passport-stamp-recap__tags">
				<?php if ( $restaurant !== '' ) : ?>
					<li><?php echo esc_html( $restaurant ); ?></li>
				<?php endif; ?>
				<?php if ( $first_time ) : ?>
					<li><?php esc_html_e( 'First time!', 'eatforeign' ); ?></li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
