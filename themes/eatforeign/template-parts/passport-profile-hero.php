<?php
/**
 * Social-style passport profile header.
 *
 * @package EatForeignTheme
 *
 * @var array<string, mixed> $passport
 * @var bool $is_owner
 * @var WP_User|null $user
 */

declare(strict_types=1);

use EatForeignTheme\Data;
use EatForeignTheme\Helpers;

$passport  = isset( $args['passport'] ) && is_array( $args['passport'] ) ? $args['passport'] : [];
$is_owner  = ! empty( $args['is_owner'] );
$user      = isset( $args['user'] ) && $args['user'] instanceof WP_User ? $args['user'] : null;
$name      = (string) ( $passport['displayName'] ?? '' );
$slug      = (string) ( $passport['slug'] ?? '' );
$home_city = (string) ( $passport['homeCity'] ?? '' );
$bio       = (string) ( $passport['bio'] ?? '' );
$entries   = isset( $passport['entries'] ) && is_array( $passport['entries'] ) ? $passport['entries'] : [];
$avg       = Helpers::average_rating_from_entries( $entries );
$initials  = Helpers::initials( $name );
$avatar    = '';

if ( $user instanceof WP_User ) {
	$avatar = (string) get_avatar_url(
		$user->ID,
		[
			'size'    => 256,
			'default' => '404',
		]
	);
}

$has_avatar = $avatar !== '' && ! str_contains( $avatar, '404' );
?>
<section class="ef-passport-profile">
	<div class="ef-passport-profile__cover" aria-hidden="true"></div>
	<div class="ef-passport-profile__body">
		<div class="ef-passport-profile__identity">
			<?php if ( $has_avatar ) : ?>
				<img class="ef-passport-profile__avatar ef-passport-profile__avatar--photo" src="<?php echo esc_url( $avatar ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<div class="ef-passport-profile__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
			<?php endif; ?>
			<div class="ef-passport-profile__who">
				<h1 class="ef-passport-profile__name"><?php echo esc_html( $name ); ?></h1>
				<?php if ( $slug !== '' ) : ?>
					<p class="ef-passport-profile__handle">@<?php echo esc_html( $slug ); ?></p>
				<?php endif; ?>
				<?php if ( Data::has_text( $home_city ) ) : ?>
					<p class="ef-passport-profile__loc">
						<span class="ef-passport-profile__loc-icon" aria-hidden="true">📍</span>
						<?php echo esc_html( $home_city ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php if ( $avg > 0 ) : ?>
				<div class="ef-passport-profile__rating-badge" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: rating */ __( 'Average rating %s', 'eatforeign' ), number_format( $avg, 1 ) ) ); ?>">
					<span class="ef-stars" aria-hidden="true"><?php echo esc_html( Helpers::rating_stars( $avg ) ); ?></span>
					<span class="ef-passport-profile__rating-num"><?php echo esc_html( number_format( $avg, 1 ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( Data::has_text( $bio ) ) : ?>
			<p class="ef-passport-profile__bio"><?php echo esc_html( $bio ); ?></p>
		<?php else : ?>
			<p class="ef-passport-profile__bio ef-passport-profile__bio--placeholder ef-muted">
				<?php echo $is_owner ? esc_html__( 'Add a short bio on your profile so friends know what you’re exploring.', 'eatforeign' ) : esc_html__( 'Food explorer on EatForeign.', 'eatforeign' ); ?>
			</p>
		<?php endif; ?>

		<div class="ef-passport-profile__stats">
			<div class="ef-passport-stat">
				<span class="ef-passport-stat__value"><?php echo esc_html( (string) (int) ( $passport['countriesExplored'] ?? 0 ) ); ?></span>
				<span class="ef-passport-stat__label"><?php esc_html_e( 'Countries', 'eatforeign' ); ?></span>
			</div>
			<div class="ef-passport-stat">
				<span class="ef-passport-stat__value"><?php echo esc_html( (string) (int) ( $passport['dishesTried'] ?? 0 ) ); ?></span>
				<span class="ef-passport-stat__label"><?php esc_html_e( 'Dishes', 'eatforeign' ); ?></span>
			</div>
			<div class="ef-passport-stat">
				<span class="ef-passport-stat__value"><?php echo esc_html( (string) (int) ( $passport['celebrationsCompleted'] ?? 0 ) ); ?></span>
				<span class="ef-passport-stat__label"><?php esc_html_e( 'Events', 'eatforeign' ); ?></span>
			</div>
		</div>

		<?php if ( $is_owner ) : ?>
			<div class="ef-passport-profile__actions">
				<a class="ef-button ef-button--primary" href="<?php echo esc_url( home_url( '/account/profile' ) ); ?>">
					<?php esc_html_e( 'Edit profile', 'eatforeign' ); ?>
				</a>
				<a class="ef-button" href="<?php echo esc_url( home_url( '/directory' ) ); ?>">
					<?php esc_html_e( 'Find a dish', 'eatforeign' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</section>
