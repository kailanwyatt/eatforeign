<?php
/**
 * Account page (sub-sections: profile, passport, notifications, security).
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

if (! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/login' ) );
	exit;
}

get_header();

$user_id = get_current_user_id();
$profile = class_exists( '\EatForeign\Repositories\PassportRepository' )
	? \EatForeign\Repositories\PassportRepository::format_user_profile( get_user_by( 'id', $user_id ), true )
	: null;

$raw_tab = (string) get_query_var( 'ef_account_tab' );
$allowed = [ 'profile', 'passport', 'notifications', 'security' ];
$tab     = in_array( $raw_tab, $allowed, true ) ? $raw_tab : 'profile';

$updated = isset( $_GET['updated'] );
$error   = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
?>
<div class="ef-shell ef-stack ef-stack--account">
	<h1 class="ef-page-title ef-account-page-title"><?php esc_html_e( 'Your account', 'eatforeign' ); ?></h1>

	<div class="ef-account-layout">
		<?php get_template_part( 'template-parts/account/nav', null, [ 'tab' => $tab ] ); ?>

		<div class="ef-account-main">
			<?php
			if ( $tab === 'profile' ) {
				get_template_part(
					'template-parts/account/profile',
					null,
					[
						'user_id' => $user_id,
						'profile' => $profile,
						'updated' => $updated,
					]
				);
			} elseif ( $tab === 'passport' ) {
				get_template_part( 'template-parts/account/passport', null, [ 'profile' => $profile ] );
			} elseif ( $tab === 'notifications' ) {
				get_template_part(
					'template-parts/account/notifications',
					null,
					[
						'user_id' => $user_id,
						'updated' => $updated,
					]
				);
			} else {
				get_template_part(
					'template-parts/account/security',
					null,
					[
						'updated' => $updated,
						'error'   => $error,
					]
				);
			}
			?>
		</div>
	</div>
</div>
<?php
get_footer();
