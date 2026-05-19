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
					<?php foreach ( $passport['entries'] as $entry ) : ?>
						<li class="ef-entry">
							<a href="<?php echo esc_url( home_url( '/dishes/' . $entry['dishSlug'] ) ); ?>"><?php echo esc_html( (string) $entry['dishSlug'] ); ?></a>
							<span><?php echo esc_html( number_format( (float) $entry['rating'], 1 ) ); ?></span>
							<?php if ( Data::has_text( $entry['note'] ?? '' ) ) : ?>
								<p><?php echo esc_html( (string) $entry['note'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
