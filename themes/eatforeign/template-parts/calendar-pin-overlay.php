<?php
/**
 * Floating preview card for calendar celebration chips (hover).
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);
?>
<div id="ef-calendar-pin" class="ef-calendar-pin" hidden aria-hidden="true">
	<div class="ef-calendar-pin__card">
		<a class="ef-calendar-pin__link" data-pin-link href="#">
			<div class="ef-calendar-pin__media">
				<img class="ef-calendar-pin__image" data-pin-image src="" alt="" loading="lazy" />
			</div>
			<div class="ef-calendar-pin__body">
				<div class="ef-calendar-pin__badges">
					<span class="ef-pill ef-pill--accent" data-pin-label></span>
				</div>
				<h3 class="ef-calendar-pin__title">
					<span class="ef-calendar-pin__flag" data-pin-flag aria-hidden="true"></span>
					<span data-pin-title></span>
				</h3>
				<p class="ef-calendar-pin__subtitle ef-muted" data-pin-subtitle hidden></p>
				<p class="ef-calendar-pin__copy ef-muted" data-pin-copy hidden></p>
			</div>
		</a>
	</div>
	<span class="ef-calendar-pin__pointer" aria-hidden="true"></span>
</div>
