<?php
/**
 * Notification Engine
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class Notifications {
	public static function register(): void {
		add_action( 'init', [ self::class, 'schedule_cron' ] );
		add_action( 'eatforeign_daily_notifications', [ self::class, 'process_daily_notifications' ] );
		
		// For testing:
		add_action( 'admin_post_ef_test_notifications', [ self::class, 'manual_test_notifications' ] );
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'eatforeign_daily_notifications' ) ) {
			// Schedule to run daily at 8am roughly
			wp_schedule_event( strtotime('08:00:00'), 'daily', 'eatforeign_daily_notifications' );
		}
	}

	public static function process_daily_notifications(): void {
		// Find celebrations coming up in 3 days, and celebrations happening today
		$args = [
			'post_type'      => 'ef_celebration',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		];
		
		$celebrations = get_posts( $args );
		
		if ( empty( $celebrations ) ) {
			return;
		}

		$today_events = [];
		$upcoming_events = [];

		foreach ( $celebrations as $celeb ) {
			$date = get_post_meta( $celeb->ID, 'ef_event_date', true );
			if ( ! $date ) continue;
			
			// Normalize date to compare (since some might just say "August 6" without year, 
			// we check if it matches today's month/day)
			$celeb_time = strtotime( (string) $date );
			if ( ! $celeb_time ) continue;
			
			$celeb_md = gmdate( 'm-d', $celeb_time );
			$today_md = gmdate( 'm-d', current_time( 'timestamp' ) );
			$upcoming_md = gmdate( 'm-d', strtotime( '+3 days', current_time( 'timestamp' ) ) );

			if ( $celeb_md === $today_md ) {
				$today_events[] = $celeb;
			} else if ( $celeb_md === $upcoming_md ) {
				$upcoming_events[] = $celeb;
			}
		}

		if ( empty( $today_events ) && empty( $upcoming_events ) ) {
			return; // Nothing to notify about
		}

		// Get subscribed users
		$subscribers = get_users( [
			'meta_key'   => 'ef_email_optin',
			'meta_value' => '1',
		] );

		if ( empty( $subscribers ) ) {
			return;
		}

		foreach ( $today_events as $event ) {
			self::send_event_notification( $event, $subscribers, 'today' );
		}
		
		foreach ( $upcoming_events as $event ) {
			self::send_event_notification( $event, $subscribers, 'upcoming' );
		}
	}

	private static function send_event_notification( \WP_Post $event, array $subscribers, string $context ): void {
		$short_desc = get_post_meta( $event->ID, 'ef_short_description', true );
		$dish_ids = get_post_meta( $event->ID, 'ef_featured_dish_ids', true );
		$dish_html = '';
		
		if ( ! empty( $dish_ids ) && is_array( $dish_ids ) ) {
			$dish_id = $dish_ids[0]; // get the first featured dish
			$dish_title = get_the_title( $dish_id );
			$dish_link = get_permalink( $dish_id );
			$dish_html = "<p><strong>Featured Dish to Celebrate:</strong> <a href='" . esc_url( $dish_link ) . "'>" . esc_html( $dish_title ) . "</a></p>";
		}

		$title = $event->post_title;
		
		if ( $context === 'today' ) {
			$subject = "🗓️ Today is {$title}!";
			$heading = "Happy {$title}!";
		} else {
			$subject = "🎉 Upcoming: {$title} is in 3 days!";
			$heading = "Get Ready for {$title}!";
		}

		$message = "
		<html>
		<head>
		  <title>{$subject}</title>
		</head>
		<body style='font-family: sans-serif; line-height: 1.6; color: #333;'>
		  <h1 style='color: #d97706;'>{$heading}</h1>
		  <p>" . esc_html( (string) $short_desc ) . "</p>
		  {$dish_html}
		  <p><a href='" . esc_url( get_permalink( $event->ID ) ) . "' style='display:inline-block; padding:10px 20px; background-color:#d97706; color:#fff; text-decoration:none; border-radius:4px;'>Learn More</a></p>
		  <hr style='border:none; border-top:1px solid #eee; margin:30px 0;' />
		  <p style='font-size:12px; color:#999;'>You are receiving this because you subscribed to Culinary Alerts on EatForeign.</p>
		</body>
		</html>
		";

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		foreach ( $subscribers as $user ) {
			wp_mail( $user->user_email, $subject, $message, $headers );
		}
	}

	public static function manual_test_notifications(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		
		self::process_daily_notifications();
		wp_die( 'Test notifications triggered.' );
	}
}
