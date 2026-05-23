<?php
/**
 * All countries directory.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Template;

get_header();

$countries = Data::all_countries();
?>
<div class="ef-shell ef-stack">
	<section class="ef-hero ef-hero--directory">
		<p class="ef-hero__eyebrow"><?php esc_html_e( 'Countries', 'eatforeign' ); ?></p>
		<h1 class="ef-hero__title"><?php esc_html_e( 'Explore the world by country.', 'eatforeign' ); ?></h1>
		<p class="ef-hero__copy">
			<?php esc_html_e( 'Browse every country hub for national dishes, food holidays, and restaurant picks.', 'eatforeign' ); ?>
		</p>
	</section>

	<?php if ( $countries === [] ) : ?>
		<section class="ef-panel">
			<p><?php esc_html_e( 'No countries are published yet.', 'eatforeign' ); ?></p>
		</section>
	<?php else : ?>
		<?php Template::card_grid( $countries, 'country' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
