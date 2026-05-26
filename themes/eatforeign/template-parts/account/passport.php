<?php
/**
 * Account: food passport summary.
 *
 * @package EatForeignTheme
 *
 * @var array{profile?: array<string, mixed>|null} $args
 */

declare(strict_types=1);

use EatForeignTheme\Data;

$profile = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : null;

if (! is_array( $profile ) || $profile === [] ) {
	?>
	<section class="ef-panel ef-account-panel">
		<p class="ef-muted"><?php esc_html_e( 'Passport data is unavailable. Activate the EatForeign plugin.', 'eatforeign' ); ?></p>
	</section>
	<?php
	return;
}

$slug    = isset( $profile['slug'] ) ? (string) $profile['slug'] : '';
$entries = isset( $profile['entries'] ) && is_array( $profile['entries'] ) ? $profile['entries'] : [];
$user    = is_user_logged_in() ? wp_get_current_user() : null;

?>
<?php
get_template_part(
	'template-parts/passport',
	'profile-hero',
	[
		'passport' => $profile,
		'user'     => $user instanceof WP_User ? $user : null,
		'is_owner' => true,
	]
);
?>

<?php if ( $slug !== '' ) : ?>
	<p class="ef-account-passport-public-link">
		<a href="<?php echo esc_url( home_url( '/passport/' . rawurlencode( $slug ) ) ); ?>">
			<?php esc_html_e( 'View how your passport looks to others', 'eatforeign' ); ?>
		</a>
	</p>
<?php endif; ?>

<?php if ( $entries !== [] ) : ?>
	<section class="ef-passport-feed-section ef-account-passport-entries">
		<header class="ef-passport-feed-section__header">
			<h2 class="ef-passport-feed-section__title"><?php esc_html_e( 'Tried dishes', 'eatforeign' ); ?></h2>
		</header>
		<div class="ef-passport-feed-list">
			<?php foreach ( $entries as $entry ) : ?>
				<?php
				if ( ! is_array( $entry ) ) {
					continue;
				}

				get_template_part(
					'template-parts/passport',
					'feed-item',
					[
						'entry'    => $entry,
						'passport' => $profile,
						'user'     => $user instanceof WP_User ? $user : null,
					]
				);
				?>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>
