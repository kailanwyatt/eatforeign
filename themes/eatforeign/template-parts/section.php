<?php
/**
 * Section wrapper.
 *
 * @package EatForeignTheme
 *
 * @var array{title?: string, description?: string, content?: string, anchor_id?: string, cta_url?: string, cta_label?: string} $args
 */

declare(strict_types=1);

$title       = isset( $args['title'] ) ? (string) $args['title'] : '';
$description = isset( $args['description'] ) ? (string) $args['description'] : '';
$content     = isset( $args['content'] ) ? (string) $args['content'] : '';
$anchor_id   = isset( $args['anchor_id'] ) ? (string) $args['anchor_id'] : '';
$cta_url     = isset( $args['cta_url'] ) ? (string) $args['cta_url'] : '';
$cta_label   = isset( $args['cta_label'] ) ? (string) $args['cta_label'] : '';

if ( $content === '' ) {
	return;
}

?>
<section class="ef-section"<?php echo $anchor_id !== '' ? ' id="' . esc_attr( $anchor_id ) . '"' : ''; ?>>
	<header class="ef-section__header<?php echo ( $cta_url !== '' && $cta_label !== '' ) ? ' ef-section__header--with-cta' : ''; ?>">
		<div class="ef-section__intro">
			<h2 class="ef-section__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $description !== '' ) : ?>
				<p class="ef-section__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $cta_url !== '' && $cta_label !== '' ) : ?>
			<p class="ef-section__cta-wrap">
				<a class="ef-button ef-button--secondary ef-section__cta" href="<?php echo esc_url( $cta_url ); ?>">
					<?php echo esc_html( $cta_label ); ?>
				</a>
			</p>
		<?php endif; ?>
	</header>
	<div class="ef-section__content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in template parts. ?>
	</div>
</section>
