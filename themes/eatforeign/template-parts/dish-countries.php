<?php
/**
 * Dish country list (flags + links).
 *
 * @package EatForeignTheme
 *
 * @var array{
 *     countries?: list<array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}>,
 *     primary?: array{name: string, slug: string, flag: string, url: string, role: string, role_label: string}|null
 * } $args
 */

declare(strict_types=1);

$countries = isset( $args['countries'] ) && is_array( $args['countries'] ) ? $args['countries'] : [];
$primary   = isset( $args['primary'] ) && is_array( $args['primary'] ) ? $args['primary'] : null;

if ( $countries === [] ) {
	return;
}

?>
<ul class="ef-country-list">
	<?php foreach ( $countries as $country ) : ?>
		<?php
		$name       = (string) ( $country['name'] ?? '' );
		$flag       = (string) ( $country['flag'] ?? '' );
		$url        = (string) ( $country['url'] ?? '' );
		$role_label = (string) ( $country['role_label'] ?? '' );
		$is_primary = $primary !== null && (string) ( $primary['slug'] ?? '' ) !== '' && (string) ( $primary['slug'] ?? '' ) === (string) ( $country['slug'] ?? '' )
			|| ( $primary !== null && strcasecmp( (string) ( $primary['name'] ?? '' ), $name ) === 0 );
		?>
		<li class="ef-country-list__item<?php echo $is_primary ? ' is-primary' : ''; ?>">
			<?php if ( $url !== '' ) : ?>
				<a class="ef-country-list__link" href="<?php echo esc_url( $url ); ?>">
					<span class="ef-country-list__flag" aria-hidden="true"><?php echo esc_html( $flag !== '' ? $flag : '🌍' ); ?></span>
					<span class="ef-country-list__text">
						<span class="ef-country-list__name"><?php echo esc_html( $name ); ?></span>
						<?php if ( $role_label !== '' ) : ?>
							<span class="ef-country-list__role"><?php echo esc_html( $role_label ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			<?php else : ?>
				<div class="ef-country-list__link">
					<span class="ef-country-list__flag" aria-hidden="true"><?php echo esc_html( $flag !== '' ? $flag : '🌍' ); ?></span>
					<span class="ef-country-list__text">
						<span class="ef-country-list__name"><?php echo esc_html( $name ); ?></span>
						<?php if ( $role_label !== '' ) : ?>
							<span class="ef-country-list__role"><?php echo esc_html( $role_label ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
