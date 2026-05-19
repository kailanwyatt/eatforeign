<?php
/**
 * Account: food passport summary.
 *
 * @package EatForeignTheme
 *
 * @var array{profile?: array<string, mixed>|null} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;

$profile = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : null;

if (! is_array( $profile ) || $profile === [] ) {
	?>
	<section class="ef-panel ef-account-panel">
		<p class="ef-muted"><?php esc_html_e( 'Passport data is unavailable. Activate the EatForeign plugin.', 'eatforeign' ); ?></p>
	</section>
	<?php
	return;
}

$slug    = isset( $profile['slug'] ) ? (string) $profile['slug'] : '';
$entries = isset( $profile['entries'] ) && is_array( $profile['entries'] ) ? $profile['entries'] : [];
$dish_pt = class_exists( '\EatForeign\Support\PostType' ) ? \EatForeign\Support\PostType::DISH : 'ef_dish';

?>
<section class="ef-panel ef-account-panel">
	<h2 class="ef-account-panel__title"><?php esc_html_e( 'Food Passport', 'eatforeign' ); ?></h2>
	<p class="ef-account-panel__intro"><?php esc_html_e( 'Your tasting history and celebration progress.', 'eatforeign' ); ?></p>
	<p class="ef-card__copy">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: countries, 2: dishes, 3: celebrations */
				__( '%1$d countries explored · %2$d dishes tried · %3$d celebrations completed', 'eatforeign' ),
				(int) ( $profile['countriesExplored'] ?? 0 ),
				(int) ( $profile['dishesTried'] ?? 0 ),
				(int) ( $profile['celebrationsCompleted'] ?? 0 )
			)
		);
		?>
	</p>
	<?php if ( $slug !== '' ) : ?>
		<p class="ef-form-footer">
			<a class="ef-button ef-button--primary" href="<?php echo esc_url( home_url( '/passport/' . rawurlencode( $slug ) ) ); ?>">
				<?php esc_html_e( 'View public passport', 'eatforeign' ); ?>
			</a>
		</p>
	<?php endif; ?>
</section>

<?php if ( $entries !== [] ) : ?>
	<section class="ef-section ef-account-passport-entries">
		<header class="ef-section__header">
			<h2 class="ef-section__title"><?php esc_html_e( 'Tried dishes', 'eatforeign' ); ?></h2>
		</header>
		<div class="ef-section__content">
			<ul class="ef-entry-list">
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$dish_slug = isset( $entry['dishSlug'] ) ? (string) $entry['dishSlug'] : '';
					$dish_post = $dish_slug !== '' ? get_page_by_path( $dish_slug, OBJECT, $dish_pt ) : null;
					$dish_url  = $dish_post instanceof \WP_Post ? get_permalink( $dish_post ) : home_url( '/directory' );
					?>
					<li class="ef-entry">
						<a href="<?php echo esc_url( $dish_url ); ?>"><?php echo esc_html( $dish_slug ); ?></a>
						<span class="ef-entry__rating"><?php echo esc_html( number_format( (float) ( $entry['rating'] ?? 0 ), 1 ) ); ?></span>
						<?php if ( Data::has_text( $entry['note'] ?? '' ) ) : ?>
							<p class="ef-entry__note"><?php echo esc_html( (string) $entry['note'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>
