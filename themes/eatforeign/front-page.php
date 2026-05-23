<?php
/**
 * Front page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Template;

get_header();
?>
<div class="ef-shell ef-stack">
	<section class="ef-hero ef-hero--home">
		<p class="ef-hero__eyebrow"><?php esc_html_e( 'Global food celebration calendar', 'eatforeign' ); ?></p>
		<h1 class="ef-hero__title"><?php esc_html_e( 'Discover what the world is celebrating today.', 'eatforeign' ); ?></h1>
		<p class="ef-hero__copy">
			<?php esc_html_e( 'Follow daily food holidays, cultural milestones, and community posts, then explore the dishes and countries behind each celebration.', 'eatforeign' ); ?>
		</p>
		<p class="ef-hero__date"><?php echo esc_html( wp_date( get_option( 'date_format' ) ) ); ?></p>
		<div class="ef-hero__actions">
			<a class="ef-button ef-button--primary ef-button--lg" href="<?php echo esc_url( home_url( '/register' ) ); ?>">
				<?php esc_html_e( 'Join the celebration', 'eatforeign' ); ?>
			</a>
			<div class="ef-hero__subactions">
				<a href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'Join now', 'eatforeign' ); ?></a>
				<span class="ef-hero__dot" aria-hidden="true">·</span>
				<a href="<?php echo esc_url( home_url( '/directory' ) ); ?>"><?php esc_html_e( "See what's trending", 'eatforeign' ); ?></a>
				<span class="ef-hero__dot" aria-hidden="true">·</span>
				<a href="<?php echo esc_url( home_url( '/#explore-by-country' ) ); ?>"><?php esc_html_e( 'Explore by country', 'eatforeign' ); ?></a>
			</div>
		</div>
	</section>

	<?php if (! Data::plugin_ready() ) : ?>
		<section class="ef-panel">
			<p><?php esc_html_e( 'Activate the EatForeign plugin to populate the site with celebrations, dishes, and countries.', 'eatforeign' ); ?></p>
		</section>
	<?php else : ?>
		<?php
		Template::section(
			__( "Today's Celebrations", 'eatforeign' ),
			Data::today_celebrations(),
			'celebration',
			__( 'Every day has a reason to try something new.', 'eatforeign' )
		);
		Template::section(
			__( 'Trending Dishes', 'eatforeign' ),
			Data::trending_dishes(),
			'dish',
			__( 'Community favorites with strong ratings across celebrations.', 'eatforeign' )
		);
		Template::section(
			__( 'Most Celebrated Today', 'eatforeign' ),
			Data::most_celebrated_today(),
			'celebration',
			__( 'Celebrations with the highest participation right now.', 'eatforeign' )
		);
		Template::section(
			__( 'Explore by Country', 'eatforeign' ),
			Data::explore_countries(),
			'country',
			__( 'Jump into national dishes, holidays, and restaurant picks.', 'eatforeign' ),
			'explore-by-country',
			Data::countries_archive_url(),
			__( 'View all countries', 'eatforeign' )
		);
		Template::section(
			__( 'Upcoming Food Holidays', 'eatforeign' ),
			Data::upcoming_celebrations(),
			'celebration',
			__( 'Mark your calendar for the next global food moments.', 'eatforeign' )
		);
		Template::section(
			__( 'Featured User Celebrations', 'eatforeign' ),
			Data::featured_posts(),
			'post',
			__( 'Recent photos, ratings, and stories from the community.', 'eatforeign' )
		);
		?>
	<?php endif; ?>
</div>
<?php
get_footer();
