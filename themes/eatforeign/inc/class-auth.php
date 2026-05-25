<?php
/**
 * Theme auth and social form handlers.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

namespace EatForeignTheme;

use EatForeign\Repositories\CommunityRepository;
use EatForeign\Repositories\ModerationRepository;
use EatForeign\Support\PassportPhoto;
use EatForeign\Support\PostType;
use WP_Error;

final class Auth {
	public static function init(): void {
		add_action( 'admin_post_nopriv_ef_login', [ self::class, 'handle_login' ] );
		add_action( 'admin_post_ef_login', [ self::class, 'handle_login' ] );
		add_action( 'admin_post_nopriv_ef_register', [ self::class, 'handle_register' ] );
		add_action( 'admin_post_ef_register', [ self::class, 'handle_register' ] );
		add_action( 'admin_post_ef_logout', [ self::class, 'handle_logout' ] );
		add_action( 'admin_post_ef_update_profile', [ self::class, 'handle_update_profile' ] );
		add_action( 'admin_post_ef_update_notifications', [ self::class, 'handle_update_notifications' ] );
		add_action( 'admin_post_ef_change_password', [ self::class, 'handle_change_password' ] );
		add_action( 'admin_post_ef_rate_dish', [ self::class, 'handle_rate_dish' ] );
		add_action( 'admin_post_ef_save_passport_entry', [ self::class, 'handle_save_passport_entry' ] );
		add_action( 'admin_post_ef_toggle_celebration', [ self::class, 'handle_toggle_celebration' ] );
		add_action( 'admin_post_ef_create_celebration_post', [ self::class, 'handle_create_celebration_post' ] );
		add_action( 'admin_post_nopriv_ef_set_location', [ self::class, 'handle_set_location' ] );
		add_action( 'admin_post_ef_set_location', [ self::class, 'handle_set_location' ] );
	}

	public static function handle_login(): void {
		self::verify_nonce( 'ef_login' );

		$email    = sanitize_email( (string) ( $_POST['email'] ?? '' ) );
		$password = (string) ( $_POST['password'] ?? '' );
		$user     = wp_authenticate( $email, $password );

		if ( $user instanceof WP_Error ) {
			$login_url = home_url( '/login' );
			$redirect  = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
			if ( $redirect !== '' && wp_validate_redirect( $redirect, '' ) !== '' ) {
				$login_url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login_url );
			}
			self::redirect_with_error( $login_url, $user->get_error_message() );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );
		wp_safe_redirect( self::redirect_after_auth( home_url( '/account/profile' ) ) );
		exit;
	}

	public static function handle_register(): void {
		self::verify_nonce( 'ef_register' );

		$email    = sanitize_email( (string) ( $_POST['email'] ?? '' ) );
		$password = (string) ( $_POST['password'] ?? '' );
		$name     = sanitize_text_field( (string) ( $_POST['display_name'] ?? '' ) );

		if ( $email === '' || $password === '' ) {
			self::redirect_with_error( home_url( '/register' ), __( 'Email and password are required.', 'eatforeign' ) );
		}

		if ( strlen( $password ) < 8 ) {
			self::redirect_with_error( home_url( '/register' ), __( 'Password must be at least 8 characters.', 'eatforeign' ) );
		}

		if ( email_exists( $email ) ) {
			self::redirect_with_error( home_url( '/register' ), __( 'An account with that email already exists.', 'eatforeign' ) );
		}

		$user_id = wp_create_user( $email, $password, $email );

		if ( $user_id instanceof WP_Error ) {
			self::redirect_with_error( home_url( '/register' ), $user_id->get_error_message() );
		}

		wp_update_user(
			[
				'ID'            => $user_id,
				'display_name'  => $name !== '' ? $name : $email,
				'user_nicename' => sanitize_title( $name !== '' ? $name : strstr( $email, '@', true ) ),
			]
		);

		update_user_meta( $user_id, 'ef_display_name_override', $name );
		update_user_meta( $user_id, 'ef_profile_public', 1 );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		wp_safe_redirect( self::redirect_after_auth( home_url( '/account/profile' ) ) );
		exit;
	}

	public static function handle_logout(): void {
		self::verify_nonce( 'ef_logout' );
		wp_logout();
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	public static function handle_update_profile(): void {
		self::verify_nonce( 'ef_update_profile' );
		self::require_user();

		CommunityRepository::update_profile(
			get_current_user_id(),
			[
				'displayName'   => sanitize_text_field( (string) ( $_POST['display_name'] ?? '' ) ),
				'homeCity'      => sanitize_text_field( (string) ( $_POST['home_city'] ?? '' ) ),
				'bio'           => sanitize_textarea_field( (string) ( $_POST['bio'] ?? '' ) ),
				'locationLabel' => sanitize_text_field( (string) ( $_POST['location_label'] ?? '' ) ),
			]
		);

		if ( class_exists( ModerationRepository::class ) ) {
			ModerationRepository::set_profile_public( get_current_user_id(), isset( $_POST['profile_public'] ) );
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', home_url( '/account/profile' ) ) );
		exit;
	}

	public static function handle_update_notifications(): void {
		self::verify_nonce( 'ef_update_notifications' );
		self::require_user();

		$optin = isset( $_POST['email_optin'] ) ? '1' : '0';
		update_user_meta( get_current_user_id(), 'ef_email_optin', $optin );

		wp_safe_redirect( add_query_arg( 'updated', '1', home_url( '/account/notifications' ) ) );
		exit;
	}

	public static function handle_change_password(): void {
		self::verify_nonce( 'ef_change_password' );
		self::require_user();

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if (! $user instanceof \WP_User ) {
			self::redirect_with_error( home_url( '/account/security' ), __( 'Could not load your account.', 'eatforeign' ) );
		}

		$current = (string) ( $_POST['current_password'] ?? '' );
		$new     = (string) ( $_POST['new_password'] ?? '' );
		$confirm = (string) ( $_POST['confirm_password'] ?? '' );

		if (! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			self::redirect_with_error( home_url( '/account/security' ), __( 'Current password is incorrect.', 'eatforeign' ) );
		}

		if ( $new === '' || $new !== $confirm ) {
			self::redirect_with_error( home_url( '/account/security' ), __( 'New passwords do not match.', 'eatforeign' ) );
		}

		if ( strlen( $new ) < 8 ) {
			self::redirect_with_error( home_url( '/account/security' ), __( 'New password must be at least 8 characters.', 'eatforeign' ) );
		}

		wp_set_password( $new, $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( add_query_arg( 'updated', '1', home_url( '/account/security' ) ) );
		exit;
	}

	public static function handle_save_passport_entry(): void {
		self::verify_nonce( 'ef_save_passport_entry' );
		self::require_user();

		$dish_id  = absint( $_POST['dish_id'] ?? 0 );
		$dish     = get_post( $dish_id );
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( (string) $_POST['redirect_to'] ) : '';

		if ( $redirect === '' ) {
			$redirect = $dish instanceof \WP_Post ? get_permalink( $dish ) : home_url( '/' );
		}

		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

		if ( ! $dish instanceof \WP_Post || $dish->post_type !== PostType::DISH ) {
			self::redirect_with_error( $redirect, __( 'Dish not found.', 'eatforeign' ) );
		}

		$photos = self::collect_passport_photos_from_request();

		$entry = CommunityRepository::upsert_passport_entry(
			get_current_user_id(),
			[
				'dishId'          => $dish_id,
				'rating'          => (float) ( $_POST['rating'] ?? 0 ),
				'note'            => sanitize_textarea_field( (string) ( $_POST['note'] ?? '' ) ),
				'triedOn'         => sanitize_text_field( (string) ( $_POST['tried_on'] ?? '' ) ),
				'restaurantName'  => sanitize_text_field( (string) ( $_POST['restaurant_name'] ?? '' ) ),
				'firstTimeTrying' => isset( $_POST['first_time_trying'] ),
				'photos'          => $photos,
			]
		);

		if ( $entry === null ) {
			self::redirect_with_error( $redirect, __( 'Could not save passport entry.', 'eatforeign' ) );
		}

		$passport_url = home_url( '/dishes/' . $dish->post_name . '/passport' );
		$passport_url = add_query_arg( 'stamped', '1', $passport_url );

		if ( get_post_status( (int) ( $entry['postId'] ?? 0 ) ) === 'pending' ) {
			$passport_url = add_query_arg( 'submitted', 'pending', $passport_url );
		}

		wp_safe_redirect( $passport_url );
		exit;
	}

	/**
	 * @return list<array{url: string, caption: string}>
	 */
	private static function collect_passport_photos_from_request(): array {
		$existing_urls     = isset( $_POST['existing_photo_url'] ) && is_array( $_POST['existing_photo_url'] )
			? array_map( 'esc_url_raw', wp_unslash( $_POST['existing_photo_url'] ) )
			: [];
		$existing_captions = isset( $_POST['existing_photo_caption'] ) && is_array( $_POST['existing_photo_caption'] )
			? array_map( static fn ( mixed $value ): string => sanitize_textarea_field( (string) wp_unslash( (string) $value ) ), $_POST['existing_photo_caption'] )
			: [];

		$photos = [];

		foreach ( $existing_urls as $index => $url ) {
			if ( $url === '' ) {
				continue;
			}

			$photos[] = [
				'url'     => $url,
				'caption' => (string) ( $existing_captions[ $index ] ?? '' ),
			];
		}

		if ( ! isset( $_FILES['passport_images'] ) || ! is_array( $_FILES['passport_images'] ) ) {
			return PassportPhoto::normalize_list( $photos );
		}

		$new_captions = isset( $_POST['new_photo_caption'] ) && is_array( $_POST['new_photo_caption'] )
			? array_map( static fn ( mixed $value ): string => sanitize_textarea_field( (string) wp_unslash( (string) $value ) ), $_POST['new_photo_caption'] )
			: [];

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files = $_FILES['passport_images'];

		if ( isset( $files['name'] ) && is_array( $files['name'] ) ) {
			foreach ( array_keys( $files['name'] ) as $index ) {
				$file = [
					'name'     => $files['name'][ $index ] ?? '',
					'type'     => $files['type'][ $index ] ?? '',
					'tmp_name' => $files['tmp_name'][ $index ] ?? '',
					'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
					'size'     => $files['size'][ $index ] ?? 0,
				];

				if ( (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) {
					continue;
				}

				$attachment_id = media_handle_sideload( $file, 0 );

				if ( is_wp_error( $attachment_id ) ) {
					continue;
				}

				$url = (string) wp_get_attachment_url( (int) $attachment_id );

				if ( $url === '' ) {
					continue;
				}

				$photos[] = [
					'url'     => $url,
					'caption' => (string) ( $new_captions[ $index ] ?? '' ),
				];
			}
		} elseif ( (int) ( $files['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_OK ) {
			$attachment_id = media_handle_sideload( $files, 0 );

			if ( ! is_wp_error( $attachment_id ) ) {
				$url = (string) wp_get_attachment_url( (int) $attachment_id );

				if ( $url !== '' ) {
					$photos[] = [
						'url'     => $url,
						'caption' => (string) ( $new_captions[0] ?? '' ),
					];
				}
			}
		}

		return PassportPhoto::normalize_list( $photos );
	}

	public static function handle_rate_dish(): void {
		self::verify_nonce( 'ef_rate_dish' );
		self::require_user();

		$dish_id = absint( $_POST['dish_id'] ?? 0 );
		$rating  = (float) ( $_POST['rating'] ?? 0 );
		CommunityRepository::rate_dish( get_current_user_id(), $dish_id, $rating );

		wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	public static function handle_toggle_celebration(): void {
		self::verify_nonce( 'ef_toggle_celebration' );
		self::require_user();

		$celebration_id = absint( $_POST['celebration_id'] ?? 0 );
		CommunityRepository::toggle_celebration_completed( get_current_user_id(), $celebration_id );

		wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	public static function handle_set_location(): void {
		self::verify_nonce( 'ef_set_location' );

		$location = sanitize_text_field( (string) ( $_POST['location'] ?? '' ) );
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( (string) $_POST['redirect_to'] ) : '';

		if ( $redirect === '' ) {
			$redirect = wp_get_referer() ?: home_url( '/' );
		}

		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

		if ( is_user_logged_in() && class_exists( CommunityRepository::class ) ) {
			CommunityRepository::update_profile(
				get_current_user_id(),
				[
					'homeCity' => $location,
				]
			);
		} else {
			$cookie_path = defined( 'COOKIEPATH' ) && is_string( COOKIEPATH ) && COOKIEPATH !== '' ? COOKIEPATH : '/';
			$cookie_opts = [
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => $cookie_path,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			];
			if ( defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) && COOKIE_DOMAIN !== '' ) {
				$cookie_opts['domain'] = COOKIE_DOMAIN;
			}
			setcookie( 'ef_header_location', $location, $cookie_opts );
			$_COOKIE['ef_header_location'] = $location;
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_create_celebration_post(): void {
		self::verify_nonce( 'ef_create_celebration_post' );
		self::require_user();

		$caption = sanitize_textarea_field( (string) ( $_POST['caption'] ?? '' ) );
		if ( $caption === '' ) {
			$redirect = wp_get_referer() ?: home_url( '/' );
			self::redirect_with_error( $redirect, __( 'Please write a short story for your post.', 'eatforeign' ) );
		}

		$image_url = self::celebration_image_url_from_request();
		if ( $image_url === '' ) {
			$image_url = esc_url_raw( (string) ( $_POST['image_url'] ?? '' ) );
		}

		$post_id = CommunityRepository::create_celebration_post(
			get_current_user_id(),
			[
				'celebrationId'     => absint( $_POST['celebration_id'] ?? 0 ),
				'dishId'            => absint( $_POST['dish_id'] ?? 0 ),
				'caption'           => $caption,
				'rating'            => (float) ( $_POST['rating'] ?? 0 ),
				'imageUrl'          => $image_url,
				'restaurantName'    => sanitize_text_field( (string) ( $_POST['restaurant_name'] ?? '' ) ),
				'firstTimeTrying'   => isset( $_POST['first_time_trying'] ),
			]
		);

		$redirect = wp_get_referer() ?: home_url( '/' );

		if ( $post_id > 0 && get_post_status( $post_id ) === 'pending' ) {
			$redirect = add_query_arg( 'submitted', 'pending', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function verify_nonce( string $action ): void {
		if (! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), $action ) ) {
			wp_die( esc_html__( 'Invalid form submission.', 'eatforeign' ) );
		}
	}

	private static function require_user(): void {
		if ( get_current_user_id() <= 0 ) {
			$redirect = isset( $_SERVER['REQUEST_URI'] )
				? home_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
				: home_url( '/' );
			wp_safe_redirect(
				add_query_arg( 'redirect_to', rawurlencode( $redirect ), home_url( '/login' ) )
			);
			exit;
		}
	}

	private static function celebration_image_url_from_request(): string {
		if ( ! isset( $_FILES['celebration_image'] ) || ! is_array( $_FILES['celebration_image'] ) ) {
			return '';
		}

		$file = $_FILES['celebration_image'];

		if ( (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'celebration_image', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return '';
		}

		return (string) wp_get_attachment_url( (int) $attachment_id );
	}

	private static function redirect_after_auth( string $fallback ): string {
		$target = isset( $_POST['redirect_to'] )
			? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) )
			: '';

		if ( $target === '' && isset( $_GET['redirect_to'] ) ) {
			$target = esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) );
		}

		$validated = wp_validate_redirect( $target, '' );

		return $validated !== '' ? $validated : $fallback;
	}

	private static function redirect_with_error( string $url, string $message ): void {
		wp_safe_redirect( add_query_arg( 'error', rawurlencode( $message ), $url ) );
		exit;
	}
}
