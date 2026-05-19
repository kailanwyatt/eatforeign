<?php
/**
 * Content panel.
 *
 * @package EatForeignTheme
 *
 * @var array{title?: string, content?: string} $args
 */

declare(strict_types=1);

$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
$content = isset( $args['content'] ) ? (string) $args['content'] : '';

if ( $content === '' ) {
	return;
}

?>
<section class="ef-panel">
	<?php if ( $title !== '' ) : ?>
		<h2 class="ef-panel__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="ef-content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped at call site. ?>
	</div>
</section>
