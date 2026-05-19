<?php
/**
 * Dish directory (browse dishes).
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Template;

get_header();

$dishes = Data::directory_dishes();
$cuis   = Data::terms_for_taxonomy( 'ef_cuisine' );
$ctry   = Data::terms_for_taxonomy( 'ef_country' );
$types  = Data::terms_for_taxonomy( 'ef_dish_type' );

$q       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
$cur_c   = isset( $_GET['cuisine'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['cuisine'] ) ) : '';
$cur_cty = isset( $_GET['country'] ) ? sanitize_title( wp_unslash( (string) $_GET['country'] ) ) : '';
$cur_typ = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['type'] ) ) : '';

$form_action = home_url( '/directory' );
?>
<div class="ef-shell ef-stack">
	<section class="ef-hero ef-hero--directory">
		<h1 class="ef-hero__title"><?php esc_html_e( 'Browse dishes by cuisine, country, and story.', 'eatforeign' ); ?></h1>
		<p class="ef-hero__copy">
			<?php esc_html_e( 'Filter national dishes, seasonal favorites, and local plates before you jump into celebrations or passport stamps.', 'eatforeign' ); ?>
		</p>
	</section>

	<?php if (! Data::plugin_ready() ) : ?>
		<section class="ef-panel">
			<p><?php esc_html_e( 'Activate the EatForeign plugin to load the dish directory.', 'eatforeign' ); ?></p>
		</section>
	<?php else : ?>
		<section class="ef-panel ef-directory-filters">
			<form class="ef-directory-filters__form" method="get" action="<?php echo esc_url( $form_action ); ?>">
				<div class="ef-directory-filters__grid">
					<label class="ef-field">
						<span><?php esc_html_e( 'Search', 'eatforeign' ); ?></span>
						<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Search for a dish, country, or ingredient', 'eatforeign' ); ?>" />
					</label>
					<label class="ef-field">
						<span><?php esc_html_e( 'Cuisine', 'eatforeign' ); ?></span>
						<select name="cuisine">
							<option value=""><?php esc_html_e( 'All cuisines', 'eatforeign' ); ?></option>
							<?php foreach ( $cuis as $term ) : ?>
								<option value="<?php echo esc_attr( $term->name ); ?>"<?php selected( $cur_c, $term->name ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="ef-field">
						<span><?php esc_html_e( 'Country', 'eatforeign' ); ?></span>
						<select name="country">
							<option value=""><?php esc_html_e( 'Choose country', 'eatforeign' ); ?></option>
							<?php foreach ( $ctry as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $cur_cty, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<div class="ef-pill-row" role="group" aria-label="<?php esc_attr_e( 'Dish type', 'eatforeign' ); ?>">
					<?php
					$carry = [];
					if ( $q !== '' ) {
						$carry['q'] = $q;
					}
					if ( $cur_c !== '' ) {
						$carry['cuisine'] = $cur_c;
					}
					if ( $cur_cty !== '' ) {
						$carry['country'] = $cur_cty;
					}
					$all_url = add_query_arg( $carry, $form_action );
					?>
					<a class="ef-pill<?php echo $cur_typ === '' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $all_url ); ?>"><?php esc_html_e( 'All types', 'eatforeign' ); ?></a>
					<?php foreach ( $types as $term ) : ?>
						<?php
						$href = esc_url( add_query_arg( array_merge( $carry, [ 'type' => $term->name ] ), $form_action ) );
						?>
						<a class="ef-pill<?php echo $cur_typ === $term->name ? ' is-active' : ''; ?>" href="<?php echo $href; ?>"><?php echo esc_html( $term->name ); ?></a>
					<?php endforeach; ?>
				</div>
				<div class="ef-directory-filters__actions">
					<button type="submit" class="ef-button ef-button--primary"><?php esc_html_e( 'Apply filters', 'eatforeign' ); ?></button>
				</div>
			</form>
		</section>

		<section class="ef-directory-results">
			<header class="ef-directory-results__header">
				<h2 class="ef-directory-results__title">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of dishes */
							_n( '%d dish found', '%d dishes found', count( $dishes ), 'eatforeign' ),
							count( $dishes )
						)
					);
					?>
				</h2>
				<p class="ef-directory-results__copy"><?php esc_html_e( 'Click a dish to mark your calendar, view the history, and find restaurants.', 'eatforeign' ); ?></p>
			</header>
			<?php if ( $dishes === [] ) : ?>
				<p class="ef-muted"><?php esc_html_e( 'No dishes match those filters yet.', 'eatforeign' ); ?></p>
			<?php else : ?>
				<?php Template::dish_directory_grid( $dishes ); ?>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
