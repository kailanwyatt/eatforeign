<?php
/**
 * Site footer.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Nav;

?>
</main>
<footer class="ef-site-footer">
	<div class="ef-shell ef-site-footer__grid">
		<div class="ef-site-footer__col ef-site-footer__brand">
			<?php
			get_template_part(
				'template-parts/site',
				'logo',
				[
					'link'    => true,
					'variant' => 'footer',
				]
			);
			?>
			<p class="ef-site-footer__tagline">
				<?php esc_html_e( 'The world\'s food celebration calendar for discovering cultures, dishes, and places to eat together.', 'eatforeign' ); ?>
			</p>
		</div>
		<?php
		Nav::render_footer_column( 'footer-1', __( 'Explore', 'eatforeign' ), [ Nav::class, 'render_footer_1_fallback' ] );
		Nav::render_footer_column( 'footer-2', __( 'Account', 'eatforeign' ), [ Nav::class, 'render_footer_2_fallback' ] );
		Nav::render_footer_column( 'footer-3', __( 'Legal', 'eatforeign' ), [ Nav::class, 'render_footer_3_fallback' ] );
		?>
	</div>
	<div class="ef-shell ef-site-footer__bottom">
		<p class="ef-site-footer__copy">
			&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.
			<?php esc_html_e( 'All rights reserved.', 'eatforeign' ); ?>
		</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
