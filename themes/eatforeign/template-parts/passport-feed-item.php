<?php
/**
 * Social-style passport dish entry (feed post).
 *
 * @package EatForeignTheme
 *
 * @var array<string, mixed> $entry
 * @var array<string, mixed> $passport
 * @var WP_User|null $user
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Helpers;

$entry    = isset( $args['entry'] ) && is_array( $args['entry'] ) ? $args['entry'] : [];
$passport = isset( $args['passport'] ) && is_array( $args['passport'] ) ? $args['passport'] : [];
$user     = isset( $args['user'] ) && $args['user'] instanceof WP_User ? $args['user'] : null;

$dish_pt   = class_exists( '\EatForeign\Support\PostType' ) ? \EatForeign\Support\PostType::DISH : 'ef_dish';
$dish_slug = isset( $entry['dishSlug'] ) ? (string) $entry['dishSlug'] : '';
$dish_post = $dish_slug !== '' ? get_page_by_path( $dish_slug, OBJECT, $dish_pt ) : null;

if ( ! $dish_post instanceof WP_Post && (int) ( $entry['dishId'] ?? 0 ) > 0 ) {
	$dish_post = get_post( (int) $entry['dishId'] );
}

$dish_name = $dish_post instanceof WP_Post ? $dish_post->post_title : $dish_slug;
$dish_url  = $dish_post instanceof WP_Post ? Data::catalog_permalink( $dish_post ) : home_url( '/directory' );
$rating    = (float) ( $entry['rating'] ?? 0 );
$note      = isset( $entry['note'] ) ? trim( (string) $entry['note'] ) : '';
$tried_on  = Helpers::format_passport_date( (string) ( $entry['triedOn'] ?? '' ) );
$image     = Data::passport_entry_image( $entry, $dish_post instanceof WP_Post ? $dish_post : null );
$is_dish_image = Data::passport_entry_uses_dish_image( $entry );
$photos    = Helpers::normalize_passport_photos( isset( $entry['photos'] ) && is_array( $entry['photos'] ) ? $entry['photos'] : [] );
$extra_photos = $photos;

if ( $photos !== [] && ! $is_dish_image ) {
	$extra_photos = array_slice( $photos, 1 );
}

$author_name = (string) ( $passport['displayName'] ?? '' );
$initials    = Helpers::initials( $author_name );
$avatar      = '';

if ( $user instanceof WP_User ) {
	$avatar = (string) get_avatar_url(
		$user->ID,
		[
			'size'    => 64,
			'default' => '404',
		]
	);
}

$has_avatar = $avatar !== '' && ! str_contains( $avatar, '404' );
?>
<article class="ef-passport-feed">
	<header class="ef-passport-feed__head">
		<?php if ( $has_avatar ) : ?>
			<img class="ef-passport-feed__avatar ef-passport-feed__avatar--photo" src="<?php echo esc_url( $avatar ); ?>" alt="" loading="lazy" />
		<?php else : ?>
			<span class="ef-passport-feed__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
		<?php endif; ?>
		<div class="ef-passport-feed__meta">
			<p class="ef-passport-feed__author"><?php echo esc_html( $author_name ); ?></p>
			<?php if ( $tried_on !== '' ) : ?>
				<p class="ef-passport-feed__date"><?php echo esc_html( $tried_on ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $rating > 0 ) : ?>
			<div class="ef-passport-feed__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: rating */ __( 'Rated %s out of 5', 'eatforeign' ), number_format( $rating, 1 ) ) ); ?>">
				<span class="ef-stars" aria-hidden="true"><?php echo esc_html( Helpers::rating_stars( $rating ) ); ?></span>
				<span class="ef-passport-feed__rating-num"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
			</div>
		<?php endif; ?>
	</header>

	<a class="ef-passport-feed__media" href="<?php echo esc_url( $dish_url ); ?>">
		<img
			class="ef-passport-feed__image<?php echo $is_dish_image ? ' ef-passport-feed__image--catalog' : ''; ?>"
			src="<?php echo esc_url( $image ); ?>"
			alt="<?php echo esc_attr( $dish_name ); ?>"
			loading="lazy"
		/>
		<?php if ( $is_dish_image ) : ?>
			<span class="ef-passport-feed__media-badge"><?php esc_html_e( 'Dish photo', 'eatforeign' ); ?></span>
		<?php endif; ?>
	</a>

	<div class="ef-passport-feed__body">
		<h3 class="ef-passport-feed__title">
			<a href="<?php echo esc_url( $dish_url ); ?>"><?php echo esc_html( $dish_name ); ?></a>
		</h3>
		<?php if ( Data::has_text( $note ) ) : ?>
			<p class="ef-passport-feed__note"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $extra_photos !== [] ) : ?>
		<?php
		get_template_part(
			'template-parts/passport-entry',
			'photos',
			[ 'photos' => $extra_photos ]
		);
		?>
	<?php endif; ?>
</article>
