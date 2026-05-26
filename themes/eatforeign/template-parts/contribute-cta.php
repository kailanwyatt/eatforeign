<?php
/**
 * Contributor call-to-action for the Today page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

$suggest_url = home_url( '/suggest' );
?>
<section class="ef-contribute-cta ef-panel" aria-labelledby="ef-contribute-cta-title">
	<h2 id="ef-contribute-cta-title" class="ef-contribute-cta__title">
		<?php esc_html_e( 'Do you know any food or food holidays to celebrate?', 'eatforeign' ); ?>
	</h2>
	<p class="ef-contribute-cta__copy ef-muted">
		<?php esc_html_e( 'Tell us about a dish, regional holiday, or tradition — include a link or source if you have one.', 'eatforeign' ); ?>
	</p>
	<p class="ef-contribute-cta__actions">
		<a class="ef-button ef-button--primary" href="<?php echo esc_url( $suggest_url ); ?>">
			<?php esc_html_e( 'Share a suggestion', 'eatforeign' ); ?>
		</a>
	</p>
</section>
