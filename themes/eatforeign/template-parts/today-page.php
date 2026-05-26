<?php
/**
 * Today homepage feed.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Template;

$today_posts    = Data::today_celebrations();
$upcoming_posts = Data::upcoming_celebrations( 4 );
$date_long      = wp_date( 'l, F j, Y' );
$calendar_url   = home_url( '/calendar' );
$directory_url  = home_url( '/directory' );
$countries_url  = home_url( '/countries' );
$suggest_url    = home_url( '/suggest' );
?>
<div class="ef-today-page">
	<header class="ef-panel ef-today-page__header">
		<h1 class="ef-today-page__title"><?php esc_html_e( 'Today', 'eatforeign' ); ?></h1>
		<p class="ef-today-page__date"><?php echo esc_html( $date_long ); ?></p>
	</header>

	<?php if ( ! Data::plugin_ready() ) : ?>
		<section class="ef-panel ef-today-page__notice">
			<p><?php esc_html_e( 'Activate the EatForeign plugin to populate the site with celebrations, dishes, and countries.', 'eatforeign' ); ?></p>
		</section>
	<?php else : ?>
		<?php
		$empty_html = null;

		if ( $today_posts === [] ) {
			$empty_parts   = [];
			$empty_parts[] = '<p>' . esc_html(
				sprintf(
					/* translators: %s: formatted date */
					__( 'No food holidays on the calendar for %s.', 'eatforeign' ),
					$date_long
				)
			) . '</p>';
			$empty_parts[] = '<p class="ef-today-empty__links">'
				. '<a href="' . esc_url( $calendar_url ) . '">' . esc_html__( 'Browse calendar', 'eatforeign' ) . '</a>'
				. ' · '
				. '<a href="' . esc_url( $directory_url ) . '">' . esc_html__( 'Food directory', 'eatforeign' ) . '</a>'
				. ' · '
				. '<a href="' . esc_url( $countries_url ) . '">' . esc_html__( 'Countries', 'eatforeign' ) . '</a>'
				. '</p>';

			if ( $upcoming_posts !== [] ) {
				ob_start();
				echo '<div class="ef-today-empty__next">';
				echo '<h3 class="ef-today-empty__next-title">' . esc_html__( 'Coming up next', 'eatforeign' ) . '</h3>';
				echo '<p class="ef-today-empty__next-lede ef-muted">' . esc_html__( 'Here\'s what\'s coming soon.', 'eatforeign' ) . '</p>';
				Template::card_grid( $upcoming_posts, 'celebration' );
				echo '</div>';
				$empty_parts[] = (string) ob_get_clean();
			}

			$empty_html = implode( '', $empty_parts );
		}

		Template::section_with_empty(
			__( "Today's Celebrations", 'eatforeign' ),
			$today_posts,
			'celebration',
			__( 'Every day has a reason to try something new.', 'eatforeign' ),
			$empty_html,
			null,
			$calendar_url,
			__( 'Full calendar', 'eatforeign' )
		);

		if ( Data::today_page_needs_fallback_panel() ) :
			?>
			<section class="ef-panel ef-today-page__fallback">
				<h2 class="ef-today-page__fallback-title"><?php esc_html_e( 'We\'re still building the celebration calendar', 'eatforeign' ); ?></h2>
				<p class="ef-muted">
					<?php esc_html_e( 'Check back soon, explore the directory, or tell us about a dish or food holiday we should add.', 'eatforeign' ); ?>
				</p>
				<p class="ef-today-page__fallback-actions">
					<a class="ef-button ef-button--primary" href="<?php echo esc_url( $suggest_url ); ?>">
						<?php esc_html_e( 'Share a suggestion', 'eatforeign' ); ?>
					</a>
					<a class="ef-button" href="<?php echo esc_url( $directory_url ); ?>">
						<?php esc_html_e( 'Browse dishes', 'eatforeign' ); ?>
					</a>
				</p>
			</section>
			<?php
		endif;
		?>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/contribute', 'cta' ); ?>
</div>
