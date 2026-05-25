<?php
/**
 * Site footer.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

?>
</main>
<footer class="ef-site-footer">
	<div class="ef-shell ef-site-footer__grid">
		<div class="ef-site-footer__col ef-site-footer__brand">
			<p class="ef-site-footer__logo"><?php bloginfo( 'name' ); ?></p>
			<p class="ef-site-footer__tagline">
				<?php esc_html_e( 'The world\'s food celebration calendar for discovering cultures, dishes, and places to eat together.', 'eatforeign' ); ?>
			</p>
		</div>
		<div class="ef-site-footer__col">
			<p class="ef-site-footer__heading"><?php esc_html_e( 'Explore', 'eatforeign' ); ?></p>
			<ul class="ef-site-footer__links">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Today', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/calendar' ) ); ?>"><?php esc_html_e( 'Calendar', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/directory' ) ); ?>"><?php esc_html_e( 'Food directory', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/passport' ) ); ?>"><?php esc_html_e( 'Food passport', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/#explore-by-country' ) ); ?>"><?php esc_html_e( 'Explore by country', 'eatforeign' ); ?></a></li>
			</ul>
		</div>
		<div class="ef-site-footer__col">
			<p class="ef-site-footer__heading"><?php esc_html_e( 'Account', 'eatforeign' ); ?></p>
			<ul class="ef-site-footer__links">
				<li><a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'Sign in', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'Create account', 'eatforeign' ); ?></a></li>
			</ul>
		</div>
		<div class="ef-site-footer__col">
			<p class="ef-site-footer__heading"><?php esc_html_e( 'Legal', 'eatforeign' ); ?></p>
			<ul class="ef-site-footer__links">
				<li><a href="<?php echo esc_url( home_url( '/#terms' ) ); ?>"><?php esc_html_e( 'Terms of Service', 'eatforeign' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/#privacy' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'eatforeign' ); ?></a></li>
			</ul>
		</div>
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
