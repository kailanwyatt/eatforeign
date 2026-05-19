<?php
/**
 * Account sub-navigation.
 *
 * @package EatForeignTheme
 *
 * @var array{tab?: string} $args
 */

declare(strict_types=1);

$tab = isset( $args['tab'] ) ? (string) $args['tab'] : 'profile';

$items = [
	'profile'        => __( 'Profile', 'eatforeign' ),
	'passport'       => __( 'Food Passport', 'eatforeign' ),
	'notifications'  => __( 'Notifications', 'eatforeign' ),
	'security'       => __( 'Security', 'eatforeign' ),
];

?>
<nav class="ef-account-nav" aria-label="<?php esc_attr_e( 'Account sections', 'eatforeign' ); ?>">
	<ul class="ef-account-nav__list">
		<?php foreach ( $items as $slug => $label ) : ?>
			<?php
			$url      = esc_url( home_url( '/account/' . $slug ) );
			$is_active = $tab === $slug;
			?>
			<li class="ef-account-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
				<a
					href="<?php echo $url; ?>"
					<?php echo $is_active ? ' aria-current="page"' : ''; ?>
				><?php echo esc_html( $label ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
