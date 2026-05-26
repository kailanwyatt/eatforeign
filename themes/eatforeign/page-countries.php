<?php
/**
 * All countries directory.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;

get_header();

$countries      = Data::all_countries();
$country_count  = count( $countries );
$filter_strings = [
	'all'   => sprintf(
		/* translators: %d: country count */
		__( 'Showing all {total} countries', 'eatforeign' ),
		$country_count
	),
	'match' => __( '{visible} of {total} countries match "{query}"', 'eatforeign' ),
	'empty' => __( 'No countries match your search. Try another keyword.', 'eatforeign' ),
];
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
		<section class="ef-panel ef-countries-filter" data-countries-filter>
			<label class="ef-field ef-countries-filter__field">
				<span><?php esc_html_e( 'Search countries', 'eatforeign' ); ?></span>
				<input
					type="search"
					class="ef-countries-filter__input"
					data-countries-search
					placeholder="<?php esc_attr_e( 'Search by country, region, or dish…', 'eatforeign' ); ?>"
					autocomplete="off"
					spellcheck="false"
				/>
			</label>
			<p class="ef-countries-filter__meta" data-countries-count>
				<?php echo esc_html( str_replace( '{total}', (string) $country_count, $filter_strings['all'] ) ); ?>
			</p>
			<p class="ef-countries-filter__empty ef-muted" data-countries-empty hidden>
				<?php echo esc_html( $filter_strings['empty'] ); ?>
			</p>
		</section>

		<div class="ef-grid ef-grid--countries" data-countries-grid>
			<?php foreach ( $countries as $country ) : ?>
				<?php
				if ( ! $country instanceof WP_Post ) {
					continue;
				}
				?>
				<div
					class="ef-countries-grid__item"
					data-country-card
					data-search="<?php echo esc_attr( Data::country_search_text( $country ) ); ?>"
				>
					<?php get_template_part( 'template-parts/card', 'country', [ 'post' => $country ] ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
