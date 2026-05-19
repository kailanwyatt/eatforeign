<?php
/**
 * Section wrapper.
 *
 * @package EatForeignTheme
 *
 * @var array{title?: string, description?: string, content?: string, anchor_id?: string} $args
 */

declare(strict_types=1);

$title       = isset( $args['title'] ) ? (string) $args['title'] : '';
$description = isset( $args['description'] ) ? (string) $args['description'] : '';
$content     = isset( $args['content'] ) ? (string) $args['content'] : '';
$anchor_id   = isset( $args['anchor_id'] ) ? (string) $args['anchor_id'] : '';

if ( $content === '' ) {
	return;
}

?>
<section class="ef-section"<?php echo $anchor_id !== '' ? ' id="' . esc_attr( $anchor_id ) . '"' : ''; ?>>
	<header class="ef-section__header">
		<h2 class="ef-section__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $description !== '' ) : ?>
			<p class="ef-section__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	</header>
	<div class="ef-section__content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in template parts. ?>
	</div>
</section>
