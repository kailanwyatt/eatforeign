<?php
/**
 * Dish single.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Places;
use EatForeignTheme\Template;

get_header();

while ( have_posts() ) :
	the_post();
	$dish_id          = get_the_ID();
	$cultural_meaning = (string) get_post_meta( $dish_id, 'ef_cultural_meaning', true );
	$ingredients      = array_values( array_filter( (array) get_post_meta( $dish_id, 'ef_ingredients', true ) ) );
	$linked           = Data::posts_by_ids( (array) get_post_meta( $dish_id, 'ef_celebration_ids', true ) );
	$hero_image       = Data::post_image( get_post() );
	$origin           = (string) get_post_meta( $dish_id, 'ef_origin_country', true );
	$countries        = Data::dish_countries( $dish_id );
	$cuisines         = Data::post_term_names( $dish_id, 'ef_cuisine' );
	$dish_types       = Data::post_term_names( $dish_id, 'ef_dish_type' );
	$spice_levels     = Data::post_term_names( $dish_id, 'ef_spice_level' );
	$rating           = (float) get_post_meta( $dish_id, 'ef_average_rating', true );
	$nearby           = Places::nearby_for_dish( get_the_title() );
	?>
	<div class="ef-shell ef-dish-page">
		<article <?php post_class( 'ef-dish-hero' ); ?>>
			<?php if ( $hero_image !== '' ) : ?>
				<div class="ef-dish-hero__media">
					<img class="ef-dish-hero__image" src="<?php echo esc_url( $hero_image ); ?>" alt="<?php the_title_attribute(); ?>" />
				</div>
			<?php endif; ?>
			<div class="ef-dish-hero__body">
				<?php if ( $countries['all'] !== [] ) : ?>
					<div class="ef-dish-hero__countries" aria-label="<?php esc_attr_e( 'Countries and regions', 'eatforeign' ); ?>">
						<?php foreach ( array_slice( $countries['all'], 0, 4 ) as $country ) : ?>
							<?php
							$flag = (string) ( $country['flag'] ?? '' );
							$url  = (string) ( $country['url'] ?? '' );
							$name = (string) ( $country['name'] ?? '' );
							?>
							<?php if ( $url !== '' ) : ?>
								<a class="ef-dish-country-chip" href="<?php echo esc_url( $url ); ?>">
									<span class="ef-dish-country-chip__flag" aria-hidden="true"><?php echo esc_html( $flag !== '' ? $flag : '🌍' ); ?></span>
									<span><?php echo esc_html( $name ); ?></span>
								</a>
							<?php else : ?>
								<span class="ef-dish-country-chip">
									<span class="ef-dish-country-chip__flag" aria-hidden="true"><?php echo esc_html( $flag !== '' ? $flag : '🌍' ); ?></span>
									<span><?php echo esc_html( $name ); ?></span>
								</span>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php elseif ( $origin !== '' ) : ?>
					<p class="ef-hero__meta"><?php echo esc_html( $origin ); ?></p>
				<?php endif; ?>

				<h1 class="ef-dish-hero__title"><?php the_title(); ?></h1>

				<?php if ( $cuisines !== [] || $dish_types !== [] || $spice_levels !== [] || $rating > 0 ) : ?>
					<ul class="ef-dish-hero__facts">
						<?php foreach ( $cuisines as $cuisine ) : ?>
							<li><?php echo esc_html( $cuisine ); ?></li>
						<?php endforeach; ?>
						<?php foreach ( $dish_types as $dish_type ) : ?>
							<li><?php echo esc_html( $dish_type ); ?></li>
						<?php endforeach; ?>
						<?php foreach ( $spice_levels as $spice ) : ?>
							<li><?php echo esc_html( $spice ); ?></li>
						<?php endforeach; ?>
						<?php if ( $rating > 0 ) : ?>
							<li class="ef-dish-hero__rating">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: average rating */
										__( '%s average rating', 'eatforeign' ),
										number_format( $rating, 1 )
									)
								);
								?>
							</li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>

				<?php if ( Data::has_text( get_the_excerpt() ) ) : ?>
					<p class="ef-dish-hero__copy"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</article>

		<div class="ef-dish-layout">
			<div class="ef-dish-main ef-stack ef-stack--tight">
				<?php
				if ( Data::has_text( $cultural_meaning ) ) {
					Template::panel(
						__( 'What this dish means', 'eatforeign' ),
						wpautop( esc_html( $cultural_meaning ) )
					);
				}

				if ( $ingredients !== [] ) {
					$ingredient_markup = '<ul class="ef-tag-list">';

					foreach ( $ingredients as $ingredient ) {
						$ingredient_markup .= '<li>' . esc_html( (string) $ingredient ) . '</li>';
					}

					$ingredient_markup .= '</ul>';
					Template::panel( __( 'Ingredients', 'eatforeign' ), $ingredient_markup );
				}

				$content = apply_filters( 'the_content', (string) get_post()->post_content );

				if ( Data::has_text( wp_strip_all_tags( $content ) ) ) {
					Template::panel( __( 'About this dish', 'eatforeign' ), $content );
				}

				Template::section(
					__( 'Linked celebrations', 'eatforeign' ),
					$linked,
					'celebration',
					__( 'See when this dish is in the spotlight.', 'eatforeign' )
				);
				?>
			</div>

			<aside class="ef-dish-sidebar ef-stack ef-stack--tight" aria-label="<?php esc_attr_e( 'Dish details', 'eatforeign' ); ?>">
				<?php if ( $countries['all'] !== [] ) : ?>
					<section class="ef-sidebar-card">
						<h2 class="ef-sidebar-card__title"><?php esc_html_e( 'Countries & regions', 'eatforeign' ); ?></h2>
						<p class="ef-sidebar-card__intro">
							<?php esc_html_e( 'Where this dish originates and where it is widely enjoyed.', 'eatforeign' ); ?>
						</p>
						<?php
						get_template_part(
							'template-parts/dish',
							'countries',
							[
								'countries' => $countries['all'],
								'primary'   => $countries['primary'],
							]
						);
						?>
					</section>
				<?php endif; ?>

				<?php if ( $cuisines !== [] || $dish_types !== [] || $spice_levels !== [] ) : ?>
					<section class="ef-sidebar-card">
						<h2 class="ef-sidebar-card__title"><?php esc_html_e( 'Quick facts', 'eatforeign' ); ?></h2>
						<dl class="ef-fact-list">
							<?php if ( $cuisines !== [] ) : ?>
								<div class="ef-fact-list__row">
									<dt><?php esc_html_e( 'Cuisine', 'eatforeign' ); ?></dt>
									<dd><?php echo esc_html( implode( ', ', $cuisines ) ); ?></dd>
								</div>
							<?php endif; ?>
							<?php if ( $dish_types !== [] ) : ?>
								<div class="ef-fact-list__row">
									<dt><?php esc_html_e( 'Type', 'eatforeign' ); ?></dt>
									<dd><?php echo esc_html( implode( ', ', $dish_types ) ); ?></dd>
								</div>
							<?php endif; ?>
							<?php if ( $spice_levels !== [] ) : ?>
								<div class="ef-fact-list__row">
									<dt><?php esc_html_e( 'Spice', 'eatforeign' ); ?></dt>
									<dd><?php echo esc_html( implode( ', ', $spice_levels ) ); ?></dd>
								</div>
							<?php endif; ?>
							<?php if ( $origin !== '' ) : ?>
								<div class="ef-fact-list__row">
									<dt><?php esc_html_e( 'Origin', 'eatforeign' ); ?></dt>
									<dd>
										<?php
										$primary = $countries['primary'];
										if ( is_array( $primary ) && (string) ( $primary['flag'] ?? '' ) !== '' ) {
											echo esc_html( (string) $primary['flag'] . ' ' );
										}
										echo esc_html( $origin );
										?>
									</dd>
								</div>
							<?php endif; ?>
						</dl>
					</section>
				<?php endif; ?>

				<section class="ef-sidebar-card">
					<h2 class="ef-sidebar-card__title"><?php esc_html_e( 'Rate this dish', 'eatforeign' ); ?></h2>
					<?php if ( is_user_logged_in() ) : ?>
						<?php $current_rating = Data::user_rating_for_dish( $dish_id ); ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form ef-form--compact">
							<?php wp_nonce_field( 'ef_rate_dish' ); ?>
							<input type="hidden" name="action" value="ef_rate_dish" />
							<input type="hidden" name="dish_id" value="<?php echo esc_attr( (string) $dish_id ); ?>" />
							<label class="ef-field">
								<span><?php esc_html_e( 'Your rating', 'eatforeign' ); ?></span>
								<input type="number" name="rating" min="0" max="5" step="0.1" value="<?php echo esc_attr( (string) $current_rating ); ?>" required />
							</label>
							<button type="submit" class="ef-button ef-button--primary ef-button--block"><?php esc_html_e( 'Save rating', 'eatforeign' ); ?></button>
						</form>
					<?php else : ?>
						<p class="ef-sidebar-card__intro">
							<a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'Log in', 'eatforeign' ); ?></a>
							<?php esc_html_e( 'to rate this dish.', 'eatforeign' ); ?>
						</p>
					<?php endif; ?>
				</section>

				<section class="ef-sidebar-card">
					<h2 class="ef-sidebar-card__title"><?php esc_html_e( 'Nearby restaurants', 'eatforeign' ); ?></h2>
					<p class="ef-sidebar-card__intro"><?php esc_html_e( 'Places serving this dish near you.', 'eatforeign' ); ?></p>
					<?php Places::render_list( $nearby ); ?>
				</section>
			</aside>
		</div>
		</div>
	<?php
endwhile;

get_footer();
