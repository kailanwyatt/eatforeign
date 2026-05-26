<?php
/**
 * Template for the static "Today" homepage page (if used in Reading settings).
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

get_header();
?>
<div class="ef-shell ef-stack ef-stack--today">
	<?php get_template_part( 'template-parts/today', 'page' ); ?>
</div>
<?php
get_footer();
