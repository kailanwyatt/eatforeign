<?php
/**
 * Account: change password.
 *
 * @package EatForeignTheme
 *
 * @var array{updated?: bool, error?: string} $args
 */

declare(strict_types=1);

$updated = ! empty( $args['updated'] );
$error   = isset( $args['error'] ) ? (string) $args['error'] : '';

?>
<section class="ef-panel ef-account-panel">
	<h2 class="ef-account-panel__title"><?php esc_html_e( 'Security', 'eatforeign' ); ?></h2>
	<p class="ef-account-panel__intro"><?php esc_html_e( 'Change the password you use to sign in to EatForeign.', 'eatforeign' ); ?></p>
	<?php if ( $updated ) : ?>
		<p class="ef-form-success"><?php esc_html_e( 'Password updated. You remain signed in on this device.', 'eatforeign' ); ?></p>
	<?php endif; ?>
	<?php if ( $error !== '' ) : ?>
		<p class="ef-form-error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
		<?php wp_nonce_field( 'ef_change_password' ); ?>
		<input type="hidden" name="action" value="ef_change_password" />
		<label class="ef-field">
			<span><?php esc_html_e( 'Current password', 'eatforeign' ); ?></span>
			<input type="password" name="current_password" autocomplete="current-password" required />
		</label>
		<label class="ef-field">
			<span><?php esc_html_e( 'New password', 'eatforeign' ); ?></span>
			<input type="password" name="new_password" autocomplete="new-password" minlength="8" required />
		</label>
		<label class="ef-field">
			<span><?php esc_html_e( 'Confirm new password', 'eatforeign' ); ?></span>
			<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required />
		</label>
		<button type="submit" class="ef-button ef-button--primary"><?php esc_html_e( 'Update password', 'eatforeign' ); ?></button>
	</form>
</section>
