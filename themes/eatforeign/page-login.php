<?php
/**
 * Login page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

get_header();

$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
?>
<div class="ef-auth">
	<div class="ef-auth__promo">
		<p class="ef-auth__brand"><?php bloginfo( 'name' ); ?></p>
		<p class="ef-auth__lead">
			<?php esc_html_e( 'Join a global table of daily celebrations, dishes, and food stories.', 'eatforeign' ); ?>
		</p>
		<p class="ef-auth__fineprint">
			<?php esc_html_e( 'Track your food passport, share what you tried, and discover where to celebrate next.', 'eatforeign' ); ?>
		</p>
	</div>
	<div class="ef-auth__panel">
		<div class="ef-auth__card">
			<h1 class="ef-auth__title"><?php esc_html_e( 'Sign in', 'eatforeign' ); ?></h1>
			<p class="ef-auth__subtitle"><?php esc_html_e( 'Access your food passport, posts, and saved celebrations.', 'eatforeign' ); ?></p>
			<?php if ( $error !== '' ) : ?>
				<p class="ef-form-error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
				<?php wp_nonce_field( 'ef_login' ); ?>
				<input type="hidden" name="action" value="ef_login" />
				<label class="ef-field">
					<span><?php esc_html_e( 'Email', 'eatforeign' ); ?></span>
					<input type="email" name="email" autocomplete="username" placeholder="<?php esc_attr_e( 'you@example.com', 'eatforeign' ); ?>" required />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Password', 'eatforeign' ); ?></span>
					<input type="password" name="password" autocomplete="current-password" required />
				</label>
				<button type="submit" class="ef-button ef-button--primary ef-button--block"><?php esc_html_e( 'Sign in', 'eatforeign' ); ?></button>
			</form>
			<p class="ef-auth__switch">
				<?php esc_html_e( 'New to EatForeign?', 'eatforeign' ); ?>
				<a href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'Create an account', 'eatforeign' ); ?></a>
			</p>
			<p class="ef-auth__legal">
				<?php esc_html_e( 'By continuing, you agree to our', 'eatforeign' ); ?>
				<a href="<?php echo esc_url( home_url( '/#terms' ) ); ?>"><?php esc_html_e( 'Terms', 'eatforeign' ); ?></a>
				<?php esc_html_e( 'and', 'eatforeign' ); ?>
				<a href="<?php echo esc_url( home_url( '/#privacy' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'eatforeign' ); ?></a>.
			</p>
		</div>
	</div>
</div>
<?php
get_footer();
