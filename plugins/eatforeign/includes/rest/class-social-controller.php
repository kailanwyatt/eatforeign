<?php
/**
 * Social action REST routes.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\REST;

use EatForeign\Repositories\CommunityRepository;
use EatForeign\Repositories\ModerationRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class SocialController {
	public static function register_routes(): void {
		$routes = [
			'/social/celebration-posts'        => [ 'POST', 'create_celebration_post' ],
			'/social/celebration-completions'  => [ 'POST', 'toggle_celebration_completed' ],
			'/social/dish-ratings'             => [ 'POST', 'rate_dish' ],
			'/social/dish-eat-votes'           => [ 'POST', 'set_dish_eat_vote' ],
			'/social/post-likes'               => [ 'POST', 'toggle_post_like' ],
			'/social/comments'                 => [ 'POST', 'create_comment' ],
		];

		foreach ( $routes as $route => [ $method, $callback ] ) {
			register_rest_route(
				'eatforeign/v1',
				$route,
				[
					'methods'             => $method,
					'callback'            => [ self::class, $callback ],
					'permission_callback' => [ AccountController::class, 'require_user' ],
				]
			);
		}
	}

	public static function create_celebration_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = CommunityRepository::create_celebration_post(
			get_current_user_id(),
			[
				'celebrationId'   => $request->get_param( 'celebrationId' ),
				'dishId'          => $request->get_param( 'dishId' ),
				'caption'         => $request->get_param( 'caption' ),
				'rating'          => $request->get_param( 'rating' ),
				'imageUrl'        => $request->get_param( 'imageUrl' ),
				'restaurantName'  => $request->get_param( 'restaurantName' ),
				'firstTimeTrying' => $request->get_param( 'firstTimeTrying' ),
			]
		);

		if ( $post_id <= 0 ) {
			return new WP_Error( 'eatforeign_create_failed', 'Could not create celebration post.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'postId'     => $post_id,
				'visibility' => (string) get_post_meta( $post_id, 'ef_visibility', true ),
				'status'     => get_post_status( $post_id ),
			],
			201
		);
	}

	public static function toggle_celebration_completed( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$celebration_id = absint( $request->get_param( 'celebrationId' ) );

		if ( $celebration_id <= 0 ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Celebration ID is required.', [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			CommunityRepository::toggle_celebration_completed( get_current_user_id(), $celebration_id )
		);
	}

	public static function rate_dish( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dish_id = absint( $request->get_param( 'dishId' ) );
		$rating  = (float) $request->get_param( 'rating' );

		if ( $dish_id <= 0 ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Dish ID is required.', [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			CommunityRepository::rate_dish( get_current_user_id(), $dish_id, $rating )
		);
	}

	public static function set_dish_eat_vote( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dish_id = absint( $request->get_param( 'dishId' ) );
		$vote    = (string) $request->get_param( 'vote' );

		if ( $dish_id <= 0 ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Dish ID is required.', [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			CommunityRepository::set_dish_eat_vote( get_current_user_id(), $dish_id, $vote )
		);
	}

	public static function toggle_post_like( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'postId' ) );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Post ID is required.', [ 'status' => 400 ] );
		}

		$post = get_post( $post_id );

		if (! $post || ! ModerationRepository::can_view_post( $post ) ) {
			return new WP_Error( 'eatforeign_not_found', 'Post not found.', [ 'status' => 404 ] );
		}

		return new WP_REST_Response(
			CommunityRepository::toggle_post_like( get_current_user_id(), $post_id )
		);
	}

	public static function create_comment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'postId' ) );
		$body    = sanitize_textarea_field( (string) $request->get_param( 'body' ) );

		if ( $post_id <= 0 || $body === '' ) {
			return new WP_Error( 'eatforeign_invalid_request', 'Post ID and body are required.', [ 'status' => 400 ] );
		}

		$comment_id = CommunityRepository::create_comment( get_current_user_id(), $post_id, $body );

		if ( $comment_id <= 0 ) {
			return new WP_Error( 'eatforeign_create_failed', 'Could not create comment.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'commentId'  => $comment_id,
				'visibility' => (string) get_post_meta( $comment_id, 'ef_visibility', true ),
				'status'     => get_post_status( $comment_id ),
			],
			201
		);
	}
}
