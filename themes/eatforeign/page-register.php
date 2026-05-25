<?php
/**
 * Register page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

get_header();

$error       = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) ) : '';
$redirect_to = wp_validate_redirect( $redirect_to, '' );
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
			<h1 class="ef-auth__title"><?php esc_html_e( 'Create account', 'eatforeign' ); ?></h1>
			<p class="ef-auth__subtitle"><?php esc_html_e( 'Start your passport, rate dishes, and join celebrations.', 'eatforeign' ); ?></p>
			<?php if ( $error !== '' ) : ?>
				<p class="ef-form-error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
				<?php wp_nonce_field( 'ef_register' ); ?>
				<input type="hidden" name="action" value="ef_register" />
				<?php if ( $redirect_to !== '' ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<?php endif; ?>
				<label class="ef-field">
					<span><?php esc_html_e( 'Display name', 'eatforeign' ); ?></span>
					<input type="text" name="display_name" autocomplete="name" />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Email', 'eatforeign' ); ?></span>
					<input type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'eatforeign' ); ?>" required />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Password', 'eatforeign' ); ?></span>
					<input type="password" name="password" minlength="8" autocomplete="new-password" required />
				</label>
				<button type="submit" class="ef-button ef-button--primary ef-button--block"><?php esc_html_e( 'Create account', 'eatforeign' ); ?></button>
			</form>
			<p class="ef-auth__switch">
				<a href="<?php echo esc_url( $redirect_to !== '' ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), home_url( '/login' ) ) : home_url( '/login' ) ); ?>"><?php esc_html_e( 'Already have an account?', 'eatforeign' ); ?></a>
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
