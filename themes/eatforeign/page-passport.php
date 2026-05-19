<?php
/**
 * Passport directory page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Helpers;

get_header();

$passports = Data::public_passports();
?>
<div class="ef-shell ef-stack">
	<section class="ef-hero ef-hero--passport">
		<p class="ef-hero__eyebrow"><?php esc_html_e( 'Food passport', 'eatforeign' ); ?></p>
		<h1 class="ef-hero__title"><?php esc_html_e( 'See how tasters explore the world, one dish at a time.', 'eatforeign' ); ?></h1>
		<p class="ef-hero__copy">
			<?php esc_html_e( 'Browse community passports to compare countries explored, dishes tried, personal ratings, and celebration streaks.', 'eatforeign' ); ?>
		</p>
		<a class="ef-button ef-button--primary ef-button--lg" href="<?php echo esc_url( home_url( '/directory' ) ); ?>">
			<?php esc_html_e( 'Find your next dish', 'eatforeign' ); ?>
		</a>
	</section>

	<section class="ef-passport-intro">
		<h2 class="ef-passport-intro__title"><?php esc_html_e( 'Community passports', 'eatforeign' ); ?></h2>
		<p class="ef-passport-intro__copy"><?php esc_html_e( 'Open a passport to see what each taster has tried and how they rated it.', 'eatforeign' ); ?></p>
	</section>

	<?php if ( $passports === [] ) : ?>
		<section class="ef-panel">
			<p><?php esc_html_e( 'No public passports yet. Complete dishes and make your profile public to appear here.', 'eatforeign' ); ?></p>
		</section>
	<?php else : ?>
		<div class="ef-passport-grid">
			<?php foreach ( $passports as $passport ) : ?>
				<?php
				$entries = isset( $passport['entries'] ) && is_array( $passport['entries'] ) ? $passport['entries'] : [];
				$avg     = Helpers::average_rating_from_entries( $entries );
				$initials = Helpers::initials( (string) ( $passport['displayName'] ?? '' ) );
				?>
				<article class="ef-passport-card">
					<a class="ef-passport-card__link" href="<?php echo esc_url( home_url( '/passport/' . rawurlencode( (string) $passport['slug'] ) ) ); ?>">
						<header class="ef-passport-card__head">
							<div class="ef-passport-card__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
							<div class="ef-passport-card__who">
								<h3 class="ef-passport-card__name"><?php echo esc_html( (string) $passport['displayName'] ); ?></h3>
								<?php if ( Data::has_text( $passport['homeCity'] ?? '' ) ) : ?>
									<p class="ef-passport-card__loc"><?php echo esc_html( (string) $passport['homeCity'] ); ?></p>
								<?php endif; ?>
							</div>
							<?php if ( $avg > 0 ) : ?>
								<div class="ef-passport-card__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: rating */ __( 'Average rating %s', 'eatforeign' ), (string) $avg ) ); ?>">
									<span class="ef-stars" aria-hidden="true"><?php echo str_repeat( '★', (int) round( $avg ) ); ?></span>
									<span class="ef-passport-card__avg"><?php echo esc_html( number_format( $avg, 1 ) ); ?> <?php esc_html_e( 'avg', 'eatforeign' ); ?></span>
								</div>
							<?php endif; ?>
						</header>
						<?php if ( Data::has_text( $passport['bio'] ?? '' ) ) : ?>
							<p class="ef-passport-card__bio"><?php echo esc_html( (string) $passport['bio'] ); ?></p>
						<?php endif; ?>
						<div class="ef-passport-card__stats">
							<div class="ef-passport-stat">
								<span class="ef-passport-stat__value"><?php echo esc_html( (string) (int) $passport['countriesExplored'] ); ?></span>
								<span class="ef-passport-stat__label"><?php esc_html_e( 'Countries', 'eatforeign' ); ?></span>
							</div>
							<div class="ef-passport-stat">
								<span class="ef-passport-stat__value"><?php echo esc_html( (string) (int) $passport['dishesTried'] ); ?></span>
								<span class="ef-passport-stat__label"><?php esc_html_e( 'Dishes', 'eatforeign' ); ?></span>
							</div>
							<div class="ef-passport-stat">
								<span class="ef-passport-stat__value"><?php echo esc_html( (string) (int) $passport['celebrationsCompleted'] ); ?></span>
								<span class="ef-passport-stat__label"><?php esc_html_e( 'Events', 'eatforeign' ); ?></span>
							</div>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
