<?php
/**
 * Passport detail page.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;

get_header();

$slug     = sanitize_title( (string) get_query_var( 'ef_passport_slug' ) );
$passport = Data::passport_by_slug( $slug );

if ( $passport === null ) {
	?>
	<div class="ef-shell ef-stack">
		<section class="ef-panel"><p><?php esc_html_e( 'Passport not found.', 'eatforeign' ); ?></p></section>
	</div>
	<?php
	get_footer();
	return;
}

$user     = get_user_by( 'slug', $slug );
$is_owner = is_user_logged_in() && wp_get_current_user()->user_nicename === $passport['slug'];
$entries  = isset( $passport['entries'] ) && is_array( $passport['entries'] ) ? $passport['entries'] : [];
?>
<div class="ef-shell ef-stack ef-stack--passport-detail">
	<?php
	get_template_part(
		'template-parts/passport',
		'profile-hero',
		[
			'passport'  => $passport,
			'user'      => $user instanceof WP_User ? $user : null,
			'is_owner'  => $is_owner,
		]
	);
	?>

	<?php if ( $entries !== [] ) : ?>
		<section class="ef-passport-feed-section">
			<header class="ef-passport-feed-section__header">
				<h2 class="ef-passport-feed-section__title"><?php esc_html_e( 'Tried dishes', 'eatforeign' ); ?></h2>
				<p class="ef-passport-feed-section__lede ef-muted">
					<?php esc_html_e( 'Recent stamps from the table — ratings, notes, and photos from the community.', 'eatforeign' ); ?>
				</p>
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
							'passport' => $passport,
							'user'     => $user instanceof WP_User ? $user : null,
						]
					);
					?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php else : ?>
		<section class="ef-panel ef-passport-empty">
			<p class="ef-muted">
				<?php
				echo $is_owner
					? esc_html__( 'No dishes stamped yet. Head to the directory and add your first bite.', 'eatforeign' )
					: esc_html__( 'No public dish stamps yet.', 'eatforeign' );
				?>
			</p>
			<?php if ( $is_owner ) : ?>
				<p><a class="ef-button ef-button--primary" href="<?php echo esc_url( home_url( '/directory' ) ); ?>"><?php esc_html_e( 'Browse dishes', 'eatforeign' ); ?></a></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
