<?php
/**
 * Passport detail page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;

get_header();

$slug    = sanitize_title( (string) get_query_var( 'ef_passport_slug' ) );
$passport = Data::passport_by_slug( $slug );

if ( $passport === null ) {
	?>
	<div class="ef-shell ef-stack">
		<section class="ef-panel"><p><?php esc_html_e( 'Passport not found.', 'eatforeign' ); ?></p></section>
	</div>
	<?php
	get_footer();
	return;
}

$is_owner = is_user_logged_in() && wp_get_current_user()->user_nicename === $passport['slug'];
?>
<div class="ef-shell ef-stack">
	<section class="ef-panel">
		<h1 class="ef-page-title"><?php echo esc_html( (string) $passport['displayName'] ); ?></h1>
		<?php if ( Data::has_text( $passport['homeCity'] ?? '' ) ) : ?>
			<p class="ef-card__meta"><?php echo esc_html( (string) $passport['homeCity'] ); ?></p>
		<?php endif; ?>
		<?php if ( Data::has_text( $passport['bio'] ?? '' ) ) : ?>
			<p class="ef-hero__copy"><?php echo esc_html( (string) $passport['bio'] ); ?></p>
		<?php endif; ?>
		<p class="ef-card__copy">
			<?php
			echo esc_html(
				sprintf(
					__( '%1$d countries explored · %2$d dishes tried · %3$d celebrations completed', 'eatforeign' ),
					(int) $passport['countriesExplored'],
					(int) $passport['dishesTried'],
					(int) $passport['celebrationsCompleted']
				)
			);
			?>
		</p>
		<?php if ( $is_owner ) : ?>
			<p class="ef-form-footer"><a href="<?php echo esc_url( home_url( '/account/profile' ) ); ?>"><?php esc_html_e( 'Edit profile', 'eatforeign' ); ?></a></p>
		<?php endif; ?>
	</section>

	<?php if ( ( $passport['entries'] ?? [] ) !== [] ) : ?>
		<section class="ef-section">
			<header class="ef-section__header">
				<h2 class="ef-section__title"><?php esc_html_e( 'Tried dishes', 'eatforeign' ); ?></h2>
			</header>
			<div class="ef-section__content">
				<ul class="ef-entry-list">
					<?php
					$dish_pt = class_exists( '\EatForeign\Support\PostType' ) ? \EatForeign\Support\PostType::DISH : 'ef_dish';

					foreach ( $passport['entries'] as $entry ) :
						$dish_slug = (string) ( $entry['dishSlug'] ?? '' );
						$dish_post = $dish_slug !== '' ? get_page_by_path( $dish_slug, OBJECT, $dish_pt ) : null;
						$dish_url  = $dish_post instanceof \WP_Post ? get_permalink( $dish_post ) : home_url( '/directory' );
						$dish_name = $dish_post instanceof \WP_Post ? $dish_post->post_title : $dish_slug;
						$photos    = isset( $entry['photos'] ) && is_array( $entry['photos'] ) ? $entry['photos'] : [];
						?>
						<li class="ef-entry">
							<a href="<?php echo esc_url( $dish_url ); ?>"><?php echo esc_html( $dish_name ); ?></a>
							<span class="ef-entry__rating"><?php echo esc_html( number_format( (float) ( $entry['rating'] ?? 0 ), 1 ) ); ?></span>
							<?php if ( Data::has_text( $entry['note'] ?? '' ) ) : ?>
								<p class="ef-entry__note"><?php echo esc_html( (string) $entry['note'] ); ?></p>
							<?php endif; ?>
							<?php
							get_template_part(
								'template-parts/passport-entry',
								'photos',
								[ 'photos' => $photos ]
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
