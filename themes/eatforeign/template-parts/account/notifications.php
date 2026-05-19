<?php
/**
 * Account: notification preferences.
 *
 * @package EatForeignTheme
 *
 * @var array{user_id?: int, updated?: bool} $args
 */

declare(strict_types=1);

$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
$updated = ! empty( $args['updated'] );

?>
<section class="ef-panel ef-account-panel">
	<h2 class="ef-account-panel__title"><?php esc_html_e( 'Notifications', 'eatforeign' ); ?></h2>
	<p class="ef-account-panel__intro">
		<?php esc_html_e( 'Culinary alerts are sent by email when the EatForeign site runs its daily celebration digest (today’s holidays and upcoming events for subscribers).', 'eatforeign' ); ?>
	</p>
	<?php if ( $updated ) : ?>
		<p class="ef-form-success"><?php esc_html_e( 'Notification preferences saved.', 'eatforeign' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
		<?php wp_nonce_field( 'ef_update_notifications' ); ?>
		<input type="hidden" name="action" value="ef_update_notifications" />
		<label class="ef-field ef-field--checkbox">
			<input type="checkbox" name="email_optin" value="1" <?php checked( '1', get_user_meta( $user_id, 'ef_email_optin', true ) ); ?> />
			<span><?php esc_html_e( 'Subscribe to Culinary Alerts (food holidays and upcoming celebrations by email)', 'eatforeign' ); ?></span>
		</label>
		<button type="submit" class="ef-button ef-button--primary"><?php esc_html_e( 'Save notifications', 'eatforeign' ); ?></button>
	</form>
</section>
