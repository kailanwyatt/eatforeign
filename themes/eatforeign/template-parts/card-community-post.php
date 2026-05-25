<?php
/**
 * Community celebration post card (feed layout).
 *
 * @package EatForeignTheme
 *
 * @var array{post?: WP_Post} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Helpers;
use WP_User;

$post = $args['post'] ?? null;

if ( ! $post instanceof WP_Post ) {
	return;
}

$image          = Data::post_display_image( $post );
$is_placeholder = Data::post_uses_placeholder_image( $post );
$caption    = (string) get_post_meta( $post->ID, 'ef_caption', true );
$rating     = (float) get_post_meta( $post->ID, 'ef_rating', true );
$restaurant = (string) get_post_meta( $post->ID, 'ef_restaurant_name', true );
$copy       = Data::has_text( $caption ) ? $caption : get_the_excerpt( $post );
$author     = get_userdata( (int) $post->post_author );
$author_name = $author instanceof WP_User && $author->display_name !== ''
	? $author->display_name
	: __( 'Community member', 'eatforeign' );
$date_label = get_the_date( '', $post );
$stars      = $rating > 0 ? str_repeat( '★', (int) round( min( 5, $rating ) ) ) : '';

?>
<article class="ef-card ef-card--community">
	<div class="ef-card__media">
		<img
			class="ef-card__image<?php echo $is_placeholder ? ' ef-card__image--placeholder' : ''; ?>"
			src="<?php echo esc_url( $image ); ?>"
			alt=""
			loading="lazy"
		/>
	</div>
	<div class="ef-card__body">
		<header class="ef-card__author">
			<span class="ef-card__avatar" aria-hidden="true"><?php echo esc_html( Helpers::initials( $author_name ) ); ?></span>
			<div class="ef-card__author-meta">
				<strong class="ef-card__author-name"><?php echo esc_html( $author_name ); ?></strong>
				<?php if ( $date_label !== '' ) : ?>
					<span class="ef-card__date"><?php echo esc_html( $date_label ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $rating > 0 ) : ?>
				<span class="ef-card__rating-badge" title="<?php echo esc_attr( sprintf( /* translators: %s: rating */ __( 'Rated %s out of 5', 'eatforeign' ), number_format( $rating, 1 ) ) ); ?>">
					<span class="ef-card__stars" aria-hidden="true"><?php echo esc_html( $stars ); ?></span>
					<span class="ef-card__rating-num"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
				</span>
			<?php endif; ?>
		</header>
		<?php if ( Data::has_text( $copy ) ) : ?>
			<p class="ef-card__copy"><?php echo esc_html( wp_trim_words( $copy, 42 ) ); ?></p>
		<?php endif; ?>
		<?php if ( $restaurant !== '' ) : ?>
			<p class="ef-card__venue">
				<span class="ef-card__venue-label"><?php esc_html_e( 'Ate at', 'eatforeign' ); ?></span>
				<?php echo esc_html( $restaurant ); ?>
			</p>
		<?php endif; ?>
	</div>
</article>
