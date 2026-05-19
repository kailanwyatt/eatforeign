<?php
/**
 * Celebration card.
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

$image       = Data::post_image( $post );
$event_date  = (string) get_post_meta( $post->ID, 'ef_event_date', true );
$description = (string) get_post_meta( $post->ID, 'ef_short_description', true );

if ( $description === '' ) {
	$description = get_the_excerpt( $post );
}

$type_terms = wp_get_post_terms( $post->ID, 'ef_celebration_type', [ 'fields' => 'names' ] );
$type_label = is_array( $type_terms ) && isset( $type_terms[0] ) ? (string) $type_terms[0] : __( 'Celebration', 'eatforeign' );

$country_terms = wp_get_post_terms( $post->ID, 'ef_country', [ 'fields' => 'names' ] );
$country       = is_array( $country_terms ) && isset( $country_terms[0] ) ? (string) $country_terms[0] : '';
$flag_emoji    = Data::celebration_flag_emoji( $post );

$date_short = $event_date !== '' ? wp_date( 'M j', strtotime( $event_date ) ) : '';

?>
<article class="ef-card ef-card--celebration">
	<a class="ef-card__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<?php if ( $image !== '' ) : ?>
			<div class="ef-card__media">
				<img class="ef-card__image" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy" />
			</div>
		<?php endif; ?>
		<div class="ef-card__body">
			<div class="ef-card__badges">
				<span class="ef-pill ef-pill--accent"><?php echo esc_html( $type_label ); ?></span>
				<?php if ( $date_short !== '' ) : ?>
					<span class="ef-card__date"><?php echo esc_html( $date_short ); ?></span>
				<?php endif; ?>
			</div>
			<h3 class="ef-card__title">
				<?php if ( $flag_emoji !== '' ) : ?>
					<span class="ef-card__flag" aria-hidden="true"><?php echo esc_html( $flag_emoji ); ?></span>
				<?php endif; ?>
				<?php echo esc_html( get_the_title( $post ) ); ?>
			</h3>
			<?php if ( Data::has_text( $description ) ) : ?>
				<p class="ef-card__copy"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<?php if ( $country !== '' ) : ?>
				<p class="ef-card__country"><?php echo esc_html( strtoupper( $country ) ); ?></p>
			<?php endif; ?>
		</div>
	</a>
</article>
