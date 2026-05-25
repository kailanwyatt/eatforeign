<?php
/**
 * Celebration single.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Places;
use EatForeignTheme\Template;
use EatForeign\Repositories\CommunityRepository;
use WP_Post;

get_header();

while ( have_posts() ) :
	the_post();
	$celebration_id   = get_the_ID();
	$event_date       = (string) get_post_meta( $celebration_id, 'ef_event_date', true );
	$long_description = (string) get_post_meta( $celebration_id, 'ef_long_description', true );
	$short_description = (string) get_post_meta( $celebration_id, 'ef_short_description', true );
	$featured_dishes  = Data::posts_by_ids( (array) get_post_meta( $celebration_id, 'ef_featured_dish_ids', true ) );
	$community_posts  = class_exists( CommunityRepository::class ) ? CommunityRepository::get_posts_for_celebration( $celebration_id ) : [];
	$related_celebrations = Data::related_celebrations( $celebration_id, 6 );
	$related_dishes   = Data::related_dishes_for_celebration( $celebration_id, 4 );
	$hero_image       = Data::post_image( get_post() );
	$flag_emoji       = Data::celebration_flag_emoji( get_post() );
	$country_link     = Data::celebration_country_link( $celebration_id );
	$type_terms       = wp_get_post_terms( $celebration_id, 'ef_celebration_type', [ 'fields' => 'names' ] );
	$type_label       = is_array( $type_terms ) && isset( $type_terms[0] ) ? (string) $type_terms[0] : __( 'Celebration', 'eatforeign' );

	$event_parts = [];
	if ( $event_date !== '' && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $event_date, $matches ) === 1 ) {
		$event_parts = [
			'year'  => (int) $matches[1],
			'month' => (int) $matches[2],
			'day'   => (int) $matches[3],
		];
	}

	$calendar_url = home_url( '/calendar' );
	if ( $event_parts !== [] ) {
		$calendar_url = add_query_arg(
			[
				'y' => $event_parts['year'],
				'm' => $event_parts['month'],
				'd' => $event_parts['day'],
			],
			$calendar_url
		);
	}

	$date_display = $event_date !== '' ? wp_date( get_option( 'date_format' ), strtotime( $event_date ) ) : '';
	$date_chip    = $event_date !== '' ? wp_date( 'M j', strtotime( $event_date ) ) : '';
	$hero_copy    = Data::has_text( $short_description ) ? $short_description : get_the_excerpt();

	$primary_dish = $featured_dishes[0] ?? null;
	$nearby_html  = '';
	if ( $primary_dish instanceof WP_Post ) {
		ob_start();
		Places::render_list( Places::nearby_for_dish( get_the_title( $primary_dish ) ) );
		$nearby_html = (string) ob_get_clean();
	}

	$about = Data::has_text( $long_description )
		? wpautop( esc_html( $long_description ) )
		: apply_filters( 'the_content', (string) get_post()->post_content );

	$community_empty = '<p>' . esc_html__( 'No stories yet — be the first to share how you celebrated.', 'eatforeign' ) . '</p>';
	if ( ! is_user_logged_in() ) {
		$community_empty .= '<p><a class="ef-button ef-button--primary" href="' . esc_url(
			add_query_arg( 'redirect_to', rawurlencode( get_permalink() ), home_url( '/login' ) )
		) . '">' . esc_html__( 'Log in to post', 'eatforeign' ) . '</a></p>';
	} else {
		$community_empty .= '<p><a class="ef-button ef-button--primary" href="#participate">' . esc_html__( 'Share your experience', 'eatforeign' ) . '</a></p>';
	}
	?>
	<div class="ef-shell ef-celebration-page">
		<article <?php post_class( 'ef-celebration-hero' ); ?>>
			<?php if ( $hero_image !== '' ) : ?>
				<div class="ef-celebration-hero__media">
					<img class="ef-celebration-hero__image" src="<?php echo esc_url( $hero_image ); ?>" alt="<?php the_title_attribute(); ?>" />
				</div>
			<?php endif; ?>
			<div class="ef-celebration-hero__body">
				<div class="ef-celebration-hero__chips">
					<span class="ef-pill ef-pill--accent"><?php echo esc_html( $type_label ); ?></span>
					<?php if ( $date_chip !== '' ) : ?>
						<span class="ef-celebration-hero__date"><?php echo esc_html( $date_chip ); ?></span>
					<?php endif; ?>
					<?php if ( is_array( $country_link ) && (string) ( $country_link['name'] ?? '' ) !== '' ) : ?>
						<?php
						$c_name = (string) $country_link['name'];
						$c_flag = (string) ( $country_link['flag'] ?? '' );
						$c_url  = (string) ( $country_link['url'] ?? '' );
						?>
						<?php if ( $c_url !== '' ) : ?>
							<a class="ef-celebration-hero__country" href="<?php echo esc_url( $c_url ); ?>">
								<?php if ( $c_flag !== '' ) : ?><span aria-hidden="true"><?php echo esc_html( $c_flag ); ?></span><?php endif; ?>
								<?php echo esc_html( $c_name ); ?>
							</a>
						<?php else : ?>
							<span class="ef-celebration-hero__country">
								<?php if ( $c_flag !== '' ) : ?><span aria-hidden="true"><?php echo esc_html( $c_flag ); ?></span><?php endif; ?>
								<?php echo esc_html( $c_name ); ?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<h1 class="ef-celebration-hero__title">
					<?php if ( $flag_emoji !== '' ) : ?>
						<span class="ef-celebration-hero__flag" aria-hidden="true"><?php echo esc_html( $flag_emoji ); ?></span>
					<?php endif; ?>
					<?php the_title(); ?>
				</h1>
				<?php if ( Data::has_text( $hero_copy ) ) : ?>
					<p class="ef-celebration-hero__copy"><?php echo esc_html( $hero_copy ); ?></p>
				<?php endif; ?>
				<?php if ( $date_display !== '' ) : ?>
					<p class="ef-celebration-hero__when">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: formatted event date */
								__( 'Celebrated on %s', 'eatforeign' ),
								$date_display
							)
						);
						?>
					</p>
				<?php endif; ?>
				<div class="ef-celebration-hero__actions">
					<a class="ef-button ef-button--primary" href="#participate">
						<?php esc_html_e( 'Join in', 'eatforeign' ); ?>
					</a>
					<a class="ef-button" href="<?php echo esc_url( $calendar_url ); ?>">
						<?php esc_html_e( 'View on calendar', 'eatforeign' ); ?>
					</a>
				</div>
			</div>
		</article>

		<div class="ef-celebration-layout">
			<div class="ef-celebration-main">
				<?php Template::panel( __( 'About this celebration', 'eatforeign' ), $about ); ?>

				<?php
				Template::section(
					__( 'Featured dishes', 'eatforeign' ),
					$featured_dishes,
					'dish',
					$featured_dishes !== []
						? __( 'Traditional foods and flavors tied to this celebration.', 'eatforeign' )
						: null,
					'dishes'
				);

				Template::section_with_empty(
					__( 'Community stories', 'eatforeign' ),
					$community_posts,
					'community-post',
					__( 'Real experiences from people who joined this celebration.', 'eatforeign' ),
					$community_empty,
					'community'
				);
				?>
			</div>

			<aside class="ef-celebration-aside">
				<div id="participate" class="ef-celebration-aside__block">
					<?php
					ob_start();
					get_template_part(
						'template-parts/celebration',
						'participate',
						[
							'celebration_id'  => $celebration_id,
							'featured_dishes' => $featured_dishes,
							'completed'       => is_user_logged_in() ? Data::celebration_completed( $celebration_id ) : false,
						]
					);
					Template::panel( __( 'Participate', 'eatforeign' ), (string) ob_get_clean() );
					?>
				</div>

				<?php if ( Data::has_text( wp_strip_all_tags( $nearby_html ) ) ) : ?>
					<div class="ef-celebration-aside__block">
						<?php Template::panel( __( 'Nearby restaurants', 'eatforeign' ), $nearby_html ); ?>
					</div>
				<?php endif; ?>

				<nav class="ef-celebration-aside__block ef-celebration-explore" aria-label="<?php esc_attr_e( 'Explore', 'eatforeign' ); ?>">
					<h2 class="ef-celebration-explore__title"><?php esc_html_e( 'Explore', 'eatforeign' ); ?></h2>
					<ul class="ef-celebration-explore__list">
						<li><a href="<?php echo esc_url( $calendar_url ); ?>"><?php esc_html_e( 'Calendar for this date', 'eatforeign' ); ?></a></li>
						<?php if ( is_array( $country_link ) && (string) ( $country_link['url'] ?? '' ) !== '' ) : ?>
							<li>
								<a href="<?php echo esc_url( (string) $country_link['url'] ); ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: country name */
											__( 'More from %s', 'eatforeign' ),
											(string) $country_link['name']
										)
									);
									?>
								</a>
							</li>
						<?php endif; ?>
						<li><a href="<?php echo esc_url( home_url( '/directory' ) ); ?>"><?php esc_html_e( 'Food directory', 'eatforeign' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/passport' ) ); ?>"><?php esc_html_e( 'Food passport', 'eatforeign' ); ?></a></li>
					</ul>
				</nav>
			</aside>
		</div>

		<div class="ef-celebration-related">
			<?php
			$related_celebrations = array_values(
				array_filter(
					$related_celebrations,
					static fn ( WP_Post $item ): bool => $item->ID !== $celebration_id
				)
			);

			Template::section(
				__( 'More celebrations', 'eatforeign' ),
				$related_celebrations,
				'celebration',
				__( 'Other food holidays you might enjoy around the same time or region.', 'eatforeign' ),
				'related-celebrations',
				$calendar_url,
				__( 'Browse calendar', 'eatforeign' )
			);

			Template::section(
				__( 'Related dishes', 'eatforeign' ),
				$related_dishes,
				'dish',
				__( 'More dishes from the same country to explore next.', 'eatforeign' ),
				'related-dishes',
				home_url( '/directory' ),
				__( 'Browse directory', 'eatforeign' )
			);
			?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
