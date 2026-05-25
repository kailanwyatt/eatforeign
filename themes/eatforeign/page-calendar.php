<?php
/**
 * Food celebration calendar.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeignTheme\Data;

get_header();

$y = isset( $_GET['y'] ) ? max( 1970, absint( $_GET['y'] ) ) : (int) wp_date( 'Y' );
$m = isset( $_GET['m'] ) ? min( 12, max( 1, absint( $_GET['m'] ) ) ) : (int) wp_date( 'n' );

$grouped = Data::celebrations_grouped_for_month( $y, $m );
$start   = sprintf( '%04d-%02d-01', $y, $m );
$dim     = (int) wp_date( 't', strtotime( $start ) );
$first_w = (int) wp_date( 'w', strtotime( $start ) );

$today_y = (int) wp_date( 'Y' );
$today_m = (int) wp_date( 'n' );
$today_d = (int) wp_date( 'j' );

$sel = isset( $_GET['d'] ) ? min( $dim, max( 1, absint( $_GET['d'] ) ) ) : null;

if ( $sel === null ) {
	if ( $y === $today_y && $m === $today_m ) {
		$sel = $today_d;
	} else {
		$sel = 1;
		foreach ( array_keys( $grouped ) as $dk ) {
			$p = explode( '-', (string) $dk );

			if ( count( $p ) === 3 && (int) $p[0] === $y && (int) $p[1] === $m ) {
				$sel = (int) $p[2];
				break;
			}
		}
	}
}

$selected_key    = sprintf( '%04d-%02d-%02d', $y, $m, $sel );
$selected_events = $grouped[ $selected_key ] ?? [];

$calendar_day_url = static function ( int $year, int $month, int $day ): string {
	return add_query_arg(
		[
			'y' => $year,
			'm' => $month,
			'd' => $day,
		],
		home_url( '/calendar' )
	);
};

$calendar_celebration_url = static function ( int $year, int $month, int $day, WP_Post $celebration ) use ( $calendar_day_url ): string {
	return add_query_arg(
		'c',
		$celebration->post_name,
		$calendar_day_url( $year, $month, $day )
	) . '#calendar-celebration-preview';
};

$selected_slug = isset( $_GET['c'] ) ? sanitize_title( wp_unslash( (string) $_GET['c'] ) ) : '';
$focused_event = null;

if ( $selected_slug !== '' ) {
	foreach ( $selected_events as $event_post ) {
		if ( $event_post instanceof WP_Post && $event_post->post_name === $selected_slug ) {
			$focused_event = $event_post;
			break;
		}
	}
}

$prev_m = $m === 1 ? 12 : $m - 1;
$prev_y = $m === 1 ? $y - 1 : $y;
$next_m = $m === 12 ? 1 : $m + 1;
$next_y = $m === 12 ? $y + 1 : $y;

$month_label = wp_date( 'F Y', strtotime( $start ) );

$cells = [];
$prev_month_days = (int) wp_date( 't', strtotime( sprintf( '%04d-%02d-01', $prev_y, $prev_m ) ) );

for ( $i = 0; $i < $first_w; $i++ ) {
	$d0                 = $prev_month_days - $first_w + $i + 1;
	$cells[]            = [
		'muted'   => true,
		'label'   => (string) $d0,
		'events'  => [],
		'dateKey' => '',
	];
}

for ( $d = 1; $d <= $dim; $d++ ) {
	$key      = sprintf( '%04d-%02d-%02d', $y, $m, $d );
	$cells[] = [
		'muted'   => false,
		'day'     => $d,
		'dateKey' => $key,
		'events'  => $grouped[ $key ] ?? [],
	];
}

$next_day = 1;

while ( count( $cells ) % 7 !== 0 ) {
	$cells[] = [
		'muted'   => true,
		'label'   => (string) $next_day,
		'events'  => [],
		'dateKey' => '',
	];
	++$next_day;
}

$weeks = array_chunk( $cells, 7 );
?>
<div class="ef-shell ef-stack ef-stack--calendar">
	<section class="ef-hero ef-hero--calendar">
		<p class="ef-hero__eyebrow"><?php esc_html_e( 'Calendar', 'eatforeign' ); ?></p>
		<h1 class="ef-hero__title"><?php esc_html_e( 'Food celebration calendar', 'eatforeign' ); ?></h1>
		<p class="ef-hero__copy">
			<?php esc_html_e( 'Browse the month to see which food holidays, cultural events, and national celebrations land on each day.', 'eatforeign' ); ?>
		</p>
	</section>

	<?php if (! Data::plugin_ready() ) : ?>
		<section class="ef-panel">
			<p><?php esc_html_e( 'Activate the EatForeign plugin to load celebration dates.', 'eatforeign' ); ?></p>
		</section>
	<?php else : ?>
		<section class="ef-calendar-toolbar ef-panel">
			<div class="ef-calendar-toolbar__left">
				<p class="ef-calendar-toolbar__label"><?php esc_html_e( 'Viewing', 'eatforeign' ); ?></p>
				<p class="ef-calendar-toolbar__month"><?php echo esc_html( $month_label ); ?></p>
			</div>
			<div class="ef-calendar-toolbar__controls">
				<a class="ef-button" href="<?php echo esc_url( add_query_arg( [ 'y' => $prev_y, 'm' => $prev_m ], home_url( '/calendar' ) ) ); ?>"><?php esc_html_e( 'Previous', 'eatforeign' ); ?></a>
				<a class="ef-button ef-button--primary" href="<?php echo esc_url( home_url( '/calendar' ) ); ?>"><?php esc_html_e( 'Today', 'eatforeign' ); ?></a>
				<a class="ef-button" href="<?php echo esc_url( add_query_arg( [ 'y' => $next_y, 'm' => $next_m ], home_url( '/calendar' ) ) ); ?>"><?php esc_html_e( 'Next', 'eatforeign' ); ?></a>
			</div>
		</section>

		<div class="ef-calendar-wrap ef-panel">
			<div class="ef-calendar-grid" role="grid" aria-label="<?php esc_attr_e( 'Month view', 'eatforeign' ); ?>">
				<div class="ef-calendar-grid__row ef-calendar-grid__row--head" role="row">
					<?php
					$wd = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

					foreach ( $wd as $w ) {
						echo '<div class="ef-calendar-grid__head" role="columnheader">' . esc_html( $w ) . '</div>';
					}
					?>
				</div>
				<?php foreach ( $weeks as $row ) : ?>
					<div class="ef-calendar-grid__row" role="row">
						<?php foreach ( $row as $cell ) : ?>
							<?php
							if ( $cell['muted'] ) {
								echo '<div class="ef-calendar-cell ef-calendar-cell--muted" role="gridcell"><span class="ef-calendar-cell__num">' . esc_html( $cell['label'] ) . '</span></div>';
								continue;
							}

							$d        = (int) $cell['day'];
							$is_today = $y === $today_y && $m === $today_m && $d === $today_d;
							$is_sel   = $d === $sel;
							$day_href = esc_url( $calendar_day_url( $y, $m, $d ) );
							$classes  = [ 'ef-calendar-cell' ];

							if ( $is_sel ) {
								$classes[] = 'is-selected';
							}

							if ( $is_today ) {
								$classes[] = 'is-today';
							}

							if ( $cell['events'] !== [] ) {
								$classes[] = 'has-events';
							}
							?>
							<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" role="gridcell">
								<a class="ef-calendar-cell__day" href="<?php echo $day_href; ?>">
									<span class="ef-calendar-cell__num"><?php echo esc_html( (string) $d ); ?></span>
								</a>
								<?php if ( $cell['events'] !== [] ) : ?>
									<div class="ef-calendar-cell__chips">
										<?php foreach ( array_slice( $cell['events'], 0, 2 ) as $ev ) : ?>
											<?php
											if ( ! $ev instanceof WP_Post ) {
												continue;
											}

											$chip_flag     = Data::celebration_flag_emoji( $ev );
											$chip_selected = $is_sel && $focused_event instanceof WP_Post && $focused_event->ID === $ev->ID;
											$chip_href     = esc_url( $calendar_celebration_url( $y, $m, $d, $ev ) );
											$chip_classes  = 'ef-calendar-chip';

											if ( $chip_selected ) {
												$chip_classes .= ' is-selected';
											}
											?>
											<a class="<?php echo esc_attr( $chip_classes ); ?>" href="<?php echo $chip_href; ?>">
												<?php if ( $chip_flag !== '' ) : ?>
													<span class="ef-calendar-chip__flag" aria-hidden="true"><?php echo esc_html( $chip_flag ); ?></span>
												<?php endif; ?>
												<span class="ef-calendar-chip__label"><?php echo esc_html( get_the_title( $ev ) ); ?></span>
											</a>
										<?php endforeach; ?>
										<?php if ( count( $cell['events'] ) > 2 ) : ?>
											<a class="ef-calendar-chip ef-calendar-chip--more" href="<?php echo $day_href; ?>">
												+<?php echo esc_html( (string) ( count( $cell['events'] ) - 2 ) ); ?>
											</a>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<section class="ef-calendar-detail">
			<header class="ef-calendar-detail__header">
				<h2 class="ef-calendar-detail__title"><?php echo esc_html( wp_date( 'l, F j, Y', strtotime( $selected_key ) ) ); ?></h2>
				<p class="ef-calendar-detail__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: celebration count */
							_n( '%d celebration on this day.', '%d celebrations on this day.', count( $selected_events ), 'eatforeign' ),
							count( $selected_events )
						)
					);
					?>
				</p>
			</header>
			<?php if ( $selected_events === [] ) : ?>
				<p class="ef-muted"><?php esc_html_e( 'No celebrations in the catalog for this date.', 'eatforeign' ); ?></p>
			<?php else : ?>
				<?php if ( $focused_event instanceof WP_Post ) : ?>
					<?php
					get_template_part(
						'template-parts/calendar-celebration',
						'preview',
						[ 'post' => $focused_event ]
					);
					?>
				<?php else : ?>
					<p class="ef-calendar-detail__hint ef-muted">
						<?php esc_html_e( 'Select a celebration in the calendar above to preview it here.', 'eatforeign' ); ?>
					</p>
				<?php endif; ?>
				<div class="ef-grid ef-grid--calendar-detail">
					<?php
					foreach ( $selected_events as $post ) {
						if ( ! $post instanceof WP_Post ) {
							continue;
						}

						$wrap_class = 'ef-calendar-detail__card';

						if ( $focused_event instanceof WP_Post && $focused_event->ID === $post->ID ) {
							$wrap_class .= ' is-highlighted';
						}

						echo '<div class="' . esc_attr( $wrap_class ) . '">';
						get_template_part( 'template-parts/card', 'celebration', [ 'post' => $post ] );
						echo '</div>';
					}
					?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
