<?php
/**
 * Calendar focus card for a selected celebration.
 *
 * @package EatForeignTheme
 *
 * @var array{post?: WP_Post} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;

$post = $args['post'] ?? null;

if ( ! $post instanceof WP_Post ) {
	return;
}

$image          = Data::post_display_image( $post );
$is_placeholder = Data::post_uses_placeholder_image( $post );
$event_date  = (string) get_post_meta( $post->ID, 'ef_event_date', true );
$description = (string) get_post_meta( $post->ID, 'ef_short_description', true );

if ( $description === '' ) {
	$description = get_the_excerpt( $post );
}

$type_terms = wp_get_post_terms( $post->ID, 'ef_celebration_type', [ 'fields' => 'names' ] );
$type_label = is_array( $type_terms ) && isset( $type_terms[0] ) ? (string) $type_terms[0] : __( 'Celebration', 'eatforeign' );

$country_link = Data::celebration_country_link( $post->ID );
$flag_emoji   = Data::celebration_flag_emoji( $post );
$date_display = $event_date !== '' ? wp_date( get_option( 'date_format' ), strtotime( $event_date ) ) : '';
$permalink = Data::catalog_permalink( $post );

?>
<article class="ef-calendar-preview" id="calendar-celebration-preview">
	<div class="ef-calendar-preview__media">
		<img
			class="ef-calendar-preview__image<?php echo $is_placeholder ? ' ef-calendar-preview__image--placeholder' : ''; ?>"
			src="<?php echo esc_url( $image ); ?>"
			alt="<?php echo esc_attr( get_the_title( $post ) ); ?>"
			loading="lazy"
		/>
	</div>
	<div class="ef-calendar-preview__body">
		<div class="ef-calendar-preview__badges">
			<span class="ef-pill ef-pill--accent"><?php echo esc_html( $type_label ); ?></span>
			<?php if ( $date_display !== '' ) : ?>
				<span class="ef-calendar-preview__date"><?php echo esc_html( $date_display ); ?></span>
			<?php endif; ?>
		</div>
		<h3 class="ef-calendar-preview__title">
			<?php if ( $flag_emoji !== '' ) : ?>
				<span class="ef-calendar-preview__flag" aria-hidden="true"><?php echo esc_html( $flag_emoji ); ?></span>
			<?php endif; ?>
			<?php echo esc_html( get_the_title( $post ) ); ?>
		</h3>
		<?php if ( Data::has_text( $description ) ) : ?>
			<p class="ef-calendar-preview__copy"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php if ( is_array( $country_link ) && (string) ( $country_link['name'] ?? '' ) !== '' ) : ?>
			<?php
			$c_name = (string) $country_link['name'];
			$c_flag = (string) ( $country_link['flag'] ?? '' );
			$c_url  = (string) ( $country_link['url'] ?? '' );
			?>
			<p class="ef-calendar-preview__country">
				<?php if ( $c_url !== '' ) : ?>
					<a href="<?php echo esc_url( $c_url ); ?>">
						<?php if ( $c_flag !== '' ) : ?><span aria-hidden="true"><?php echo esc_html( $c_flag ); ?></span><?php endif; ?>
						<?php echo esc_html( $c_name ); ?>
					</a>
				<?php else : ?>
					<?php if ( $c_flag !== '' ) : ?><span aria-hidden="true"><?php echo esc_html( $c_flag ); ?></span><?php endif; ?>
					<?php echo esc_html( $c_name ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<div class="ef-calendar-preview__actions">
			<a class="ef-button ef-button--primary" href="<?php echo esc_url( $permalink ); ?>">
				<?php esc_html_e( 'View celebration', 'eatforeign' ); ?>
			</a>
		</div>
	</div>
</article>
