<?php
/**
 * Site header.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Helpers;
use EatForeignTheme\Nav;

$loc_val  = Helpers::header_location_value();
$req_path = '/';
if ( isset( $_SERVER['REQUEST_URI'] ) ) {
	$raw = wp_unslash( (string) $_SERVER['REQUEST_URI'] );
	$req_path = (string) strtok( $raw, '?' );
	if ( $req_path === '' ) {
		$req_path = '/';
	}
}
$scheme = is_ssl() ? 'https' : 'http';
$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : (string) parse_url( home_url(), PHP_URL_HOST );
$redirect = esc_url_raw( $scheme . '://' . $host . $req_path );

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="ef-site-header">
	<div class="ef-shell ef-site-header__inner">
		<div class="ef-site-header__brand-row">
			<a class="ef-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			<nav class="ef-nav" aria-label="<?php esc_attr_e( 'Primary', 'eatforeign' ); ?>">
				<?php Nav::render_primary(); ?>
			</nav>
			<div class="ef-site-header__tools">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-location-form">
					<?php wp_nonce_field( 'ef_set_location' ); ?>
					<input type="hidden" name="action" value="ef_set_location" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
					<label class="screen-reader-text" for="ef-header-location"><?php esc_html_e( 'Location', 'eatforeign' ); ?></label>
					<input
						id="ef-header-location"
						class="ef-location-form__input"
						type="text"
						name="location"
						value="<?php echo esc_attr( $loc_val ); ?>"
						placeholder="<?php esc_attr_e( 'Brooklyn, NY', 'eatforeign' ); ?>"
						autocomplete="address-level2"
					/>
					<button type="submit" class="ef-button ef-button--small"><?php esc_html_e( 'Update', 'eatforeign' ); ?></button>
				</form>
				<?php if ( is_user_logged_in() ) : ?>
					<a class="ef-text-link" href="<?php echo esc_url( home_url( '/account/profile' ) ); ?>"><?php esc_html_e( 'Account', 'eatforeign' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-inline-form">
						<?php wp_nonce_field( 'ef_logout' ); ?>
						<input type="hidden" name="action" value="ef_logout" />
						<button type="submit" class="ef-text-link"><?php esc_html_e( 'Sign out', 'eatforeign' ); ?></button>
					</form>
				<?php else : ?>
					<a class="ef-text-link" href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'Sign in', 'eatforeign' ); ?></a>
					<a class="ef-button ef-button--primary ef-button--join" href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'Join', 'eatforeign' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</header>
<main class="ef-main">
