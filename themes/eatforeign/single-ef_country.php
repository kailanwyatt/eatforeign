<?php
/**
 * Country single.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Places;
use EatForeignTheme\Template;
use WP_Post;

get_header();

while ( have_posts() ) :
	the_post();
	$overview      = (string) get_post_meta( get_the_ID(), 'ef_overview', true );
	$celebrations  = Data::posts_by_ids( (array) get_post_meta( get_the_ID(), 'ef_celebration_ids', true ) );
	$dishes        = Data::posts_by_ids( (array) get_post_meta( get_the_ID(), 'ef_dish_ids', true ) );
	$hero_image    = Data::post_image( get_post() );
	?>
	<div class="ef-shell ef-stack">
		<article <?php post_class( 'ef-hero ef-hero--detail' ); ?>>
			<?php if ( $hero_image !== '' ) : ?>
				<img class="ef-hero__image" src="<?php echo esc_url( $hero_image ); ?>" alt="<?php the_title_attribute(); ?>" />
			<?php endif; ?>
			<div class="ef-hero__body">
				<p class="ef-hero__meta"><?php esc_html_e( 'Country spotlight', 'eatforeign' ); ?></p>
				<h1 class="ef-hero__title"><?php the_title(); ?></h1>
				<?php if ( Data::has_text( $overview ) ) : ?>
					<p class="ef-hero__copy"><?php echo esc_html( $overview ); ?></p>
				<?php endif; ?>
			</div>
		</article>

		<?php
		Template::section(
			__( 'Celebrations', 'eatforeign' ),
			$celebrations,
			'celebration'
		);
		Template::section(
			__( 'National dishes', 'eatforeign' ),
			$dishes,
			'dish'
		);

		$search_term = $dishes[0] instanceof WP_Post
			? get_the_title( $dishes[0] )
			: get_the_title() . ' restaurant';
		$nearby      = Places::nearby_for_dish( $search_term );
		ob_start();
		Places::render_list( $nearby );
		Template::panel( __( 'Nearby restaurants', 'eatforeign' ), (string) ob_get_clean() );
		?>
	</div>
	<?php
endwhile;

get_footer();
