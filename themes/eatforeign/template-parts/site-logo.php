<?php
/**
 * Site logo image (linked or standalone).
 *
 * @package EatForeignTheme
 *
 * @var array{
 *   link?: bool,
 *   class?: string,
 *   img_class?: string,
 *   variant?: string,
 * } $args
 */

declare(strict_types=1);

use EatForeignTheme\Theme;

$link     = ! isset( $args['link'] ) || (bool) $args['link'];
$variant  = isset( $args['variant'] ) ? sanitize_html_class( (string) $args['variant'] ) : 'header';
$variants = [ 'header', 'footer', 'auth' ];

if ( ! in_array( $variant, $variants, true ) ) {
	$variant = 'header';
}

$wrap_class = isset( $args['class'] ) ? trim( (string) $args['class'] ) : 'ef-brand';
$img_class  = isset( $args['img_class'] ) ? trim( (string) $args['img_class'] ) : 'ef-brand__img';

$wrap_class = trim( $wrap_class . ' ef-logo ef-logo--' . $variant );
$img_class  = trim( $img_class . ' ef-logo__img' );

$logo_url = Theme::logo_url();
$alt      = get_bloginfo( 'name', 'display' );

$img = sprintf(
	'<img class="%1$s" src="%2$s" alt="%3$s" width="475" height="223" decoding="async"%4$s />',
	esc_attr( $img_class ),
	esc_url( $logo_url ),
	esc_attr( $alt !== '' ? $alt : 'EatForeign' ),
	$variant === 'header' ? ' fetchpriority="high"' : ''
);

if ( $link ) {
	printf(
		'<a class="%1$s" href="%2$s">%3$s</a>',
		esc_attr( $wrap_class ),
		esc_url( home_url( '/' ) ),
		$img
	);
} else {
	printf( '<span class="%1$s">%2$s</span>', esc_attr( $wrap_class ), $img );
}
