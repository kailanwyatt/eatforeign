<?php
/**
 * Account: profile form.
 *
 * @package EatForeignTheme
 *
 * @var array{user_id?: int, profile?: array<string, mixed>|null, updated?: bool} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;

$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
$profile = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : null;
$updated = ! empty( $args['updated'] );

$public_checked = true;

if ( class_exists( '\EatForeign\Repositories\ModerationRepository' ) ) {
	$public_checked = \EatForeign\Repositories\ModerationRepository::is_profile_public( $user_id );
}

?>
<section class="ef-panel ef-account-panel">
	<h2 class="ef-account-panel__title"><?php esc_html_e( 'Profile', 'eatforeign' ); ?></h2>
	<p class="ef-account-panel__intro"><?php esc_html_e( 'How you appear on your passport and in the community directory.', 'eatforeign' ); ?></p>
	<?php if ( $updated ) : ?>
		<p class="ef-form-success"><?php esc_html_e( 'Profile updated.', 'eatforeign' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form">
		<?php wp_nonce_field( 'ef_update_profile' ); ?>
		<input type="hidden" name="action" value="ef_update_profile" />
		<label class="ef-field">
			<span><?php esc_html_e( 'Display name', 'eatforeign' ); ?></span>
			<input type="text" name="display_name" value="<?php echo esc_attr( (string) ( $profile['displayName'] ?? '' ) ); ?>" />
		</label>
		<label class="ef-field">
			<span><?php esc_html_e( 'Home city', 'eatforeign' ); ?></span>
			<input type="text" name="home_city" value="<?php echo esc_attr( (string) ( $profile['homeCity'] ?? '' ) ); ?>" />
		</label>
		<label class="ef-field">
			<span><?php esc_html_e( 'Preferred location', 'eatforeign' ); ?></span>
			<input type="text" name="location_label" value="<?php echo esc_attr( (string) get_user_meta( $user_id, 'ef_preferred_location_label', true ) ); ?>" />
		</label>
		<label class="ef-field">
			<span><?php esc_html_e( 'Bio', 'eatforeign' ); ?></span>
			<textarea name="bio" rows="4"><?php echo esc_textarea( (string) ( $profile['bio'] ?? '' ) ); ?></textarea>
		</label>
		<?php if ( class_exists( '\EatForeign\Repositories\ModerationRepository' ) ) : ?>
			<label class="ef-field ef-field--checkbox">
				<input type="checkbox" name="profile_public" value="1" <?php checked( $public_checked ); ?> />
				<span><?php esc_html_e( 'List my passport in the community directory', 'eatforeign' ); ?></span>
			</label>
		<?php endif; ?>
		<button type="submit" class="ef-button ef-button--primary"><?php esc_html_e( 'Save profile', 'eatforeign' ); ?></button>
	</form>
</section>
