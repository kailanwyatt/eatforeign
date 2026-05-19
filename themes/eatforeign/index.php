<?php
/**
 * Default template.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

get_header();
?>
<div class="ef-shell ef-stack">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'ef-panel' ); ?>>
				<h1 class="ef-page-title"><?php the_title(); ?></h1>
				<div class="ef-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<section class="ef-panel">
			<p><?php esc_html_e( 'No content found.', 'eatforeign' ); ?></p>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
