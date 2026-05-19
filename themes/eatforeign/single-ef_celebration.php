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
	$event_date      = (string) get_post_meta( get_the_ID(), 'ef_event_date', true );
	$long_description = (string) get_post_meta( get_the_ID(), 'ef_long_description', true );
	$featured_dishes = Data::posts_by_ids( (array) get_post_meta( get_the_ID(), 'ef_featured_dish_ids', true ) );
	$community_posts = class_exists( CommunityRepository::class ) ? CommunityRepository::get_posts_for_celebration( get_the_ID() ) : [];
	$hero_image      = Data::post_image( get_post() );
	?>
	<div class="ef-shell ef-stack">
		<article <?php post_class( 'ef-hero ef-hero--detail' ); ?>>
			<?php if ( $hero_image !== '' ) : ?>
				<img class="ef-hero__image" src="<?php echo esc_url( $hero_image ); ?>" alt="<?php the_title_attribute(); ?>" />
			<?php endif; ?>
			<div class="ef-hero__body">
				<?php if ( $event_date !== '' ) : ?>
					<p class="ef-hero__meta"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $event_date ) ) ); ?></p>
				<?php endif; ?>
				<h1 class="ef-hero__title"><?php the_title(); ?></h1>
				<?php if ( Data::has_text( get_the_excerpt() ) ) : ?>
					<p class="ef-hero__copy"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</article>

		<?php
		$about = Data::has_text( $long_description )
			? wpautop( esc_html( $long_description ) )
			: apply_filters( 'the_content', (string) get_post()->post_content );
		Template::panel( __( 'About this celebration', 'eatforeign' ), $about );
		Template::section(
			__( 'Featured dishes', 'eatforeign' ),
			$featured_dishes,
			'dish'
		);

		if ( is_user_logged_in() ) {
			$completed = Data::celebration_completed( get_the_ID() );
			ob_start();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
				<?php wp_nonce_field( 'ef_toggle_celebration' ); ?>
				<input type="hidden" name="action" value="ef_toggle_celebration" />
				<input type="hidden" name="celebration_id" value="<?php echo esc_attr( (string) get_the_ID() ); ?>" />
				<button type="submit" class="ef-button"><?php echo esc_html( $completed ? __( 'Mark as not completed', 'eatforeign' ) : __( 'I celebrated this', 'eatforeign' ) ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
				<?php wp_nonce_field( 'ef_create_celebration_post' ); ?>
				<input type="hidden" name="action" value="ef_create_celebration_post" />
				<input type="hidden" name="celebration_id" value="<?php echo esc_attr( (string) get_the_ID() ); ?>" />
				<label class="ef-field">
					<span><?php esc_html_e( 'Share a celebration post', 'eatforeign' ); ?></span>
					<textarea name="caption" rows="4" required></textarea>
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Rating', 'eatforeign' ); ?></span>
					<input type="number" name="rating" min="0" max="5" step="0.1" />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Image URL', 'eatforeign' ); ?></span>
					<input type="url" name="image_url" />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Restaurant', 'eatforeign' ); ?></span>
					<input type="text" name="restaurant_name" />
				</label>
				<button type="submit" class="ef-button ef-button--primary"><?php esc_html_e( 'Submit post', 'eatforeign' ); ?></button>
			</form>
			<?php
			if ( isset( $_GET['submitted'] ) && $_GET['submitted'] === 'pending' ) {
				echo '<p class="ef-form-success">' . esc_html__( 'Your post is pending moderation.', 'eatforeign' ) . '</p>';
			}
			Template::panel( __( 'Participate', 'eatforeign' ), (string) ob_get_clean() );
		} else {
			Template::panel(
				__( 'Participate', 'eatforeign' ),
				'<p><a href="' . esc_url( home_url( '/login' ) ) . '">' . esc_html__( 'Log in', 'eatforeign' ) . '</a> ' . esc_html__( 'to celebrate and share your experience.', 'eatforeign' ) . '</p>'
			);
		}

		Template::section(
			__( 'Community feed', 'eatforeign' ),
			$community_posts,
			'post'
		);

		$primary_dish = $featured_dishes[0] ?? null;
		if ( $primary_dish instanceof WP_Post ) {
			$nearby = Places::nearby_for_dish( get_the_title( $primary_dish ) );
			ob_start();
			Places::render_list( $nearby );
			Template::panel( __( 'Nearby restaurants', 'eatforeign' ), (string) ob_get_clean() );
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
