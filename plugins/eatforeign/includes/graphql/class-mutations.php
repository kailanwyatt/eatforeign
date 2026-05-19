<?php
/**
 * GraphQL write operations.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\GraphQL;

use EatForeign\Repositories\CommunityRepository;
use EatForeign\Repositories\PassportRepository;

final class Mutations {
	public static function register(): void {
		register_graphql_mutation(
			'createCelebrationPost',
			[
				'inputFields'         => [
					'celebrationId'    => [ 'type' => [ 'non_null' => 'Int' ] ],
					'dishId'           => [ 'type' => 'Int' ],
					'caption'          => [ 'type' => [ 'non_null' => 'String' ] ],
					'rating'           => [ 'type' => 'Float' ],
					'imageUrl'         => [ 'type' => 'String' ],
					'restaurantName'   => [ 'type' => 'String' ],
					'firstTimeTrying'  => [ 'type' => 'Boolean' ],
				],
				'outputFields'        => [
					'postId' => [ 'type' => 'Int' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					$post_id = CommunityRepository::create_celebration_post( get_current_user_id(), $input );
					return [ 'postId' => $post_id ];
				},
			]
		);

		register_graphql_mutation(
			'toggleCelebrationCompleted',
			[
				'inputFields'         => [
					'celebrationId' => [ 'type' => [ 'non_null' => 'Int' ] ],
				],
				'outputFields'        => [
					'celebrationId' => [ 'type' => 'Int' ],
					'completed'     => [ 'type' => 'Boolean' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					return CommunityRepository::toggle_celebration_completed( get_current_user_id(), (int) $input['celebrationId'] );
				},
			]
		);

		register_graphql_mutation(
			'rateDish',
			[
				'inputFields'         => [
					'dishId' => [ 'type' => [ 'non_null' => 'Int' ] ],
					'rating' => [ 'type' => [ 'non_null' => 'Float' ] ],
				],
				'outputFields'        => [
					'dishId' => [ 'type' => 'Int' ],
					'rating' => [ 'type' => 'Float' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					return CommunityRepository::rate_dish( get_current_user_id(), (int) $input['dishId'], (float) $input['rating'] );
				},
			]
		);

		register_graphql_mutation(
			'setDishEatVote',
			[
				'inputFields'         => [
					'dishId' => [ 'type' => [ 'non_null' => 'Int' ] ],
					'vote'   => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'outputFields'        => [
					'dishId' => [ 'type' => 'Int' ],
					'vote'   => [ 'type' => 'String' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					return CommunityRepository::set_dish_eat_vote( get_current_user_id(), (int) $input['dishId'], (string) $input['vote'] );
				},
			]
		);

		register_graphql_mutation(
			'togglePostLike',
			[
				'inputFields'         => [
					'postId' => [ 'type' => [ 'non_null' => 'Int' ] ],
				],
				'outputFields'        => [
					'postId'     => [ 'type' => 'Int' ],
					'liked'      => [ 'type' => 'Boolean' ],
					'likesCount' => [ 'type' => 'Int' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					return CommunityRepository::toggle_post_like( get_current_user_id(), (int) $input['postId'] );
				},
			]
		);

		register_graphql_mutation(
			'createComment',
			[
				'inputFields'         => [
					'postId' => [ 'type' => [ 'non_null' => 'Int' ] ],
					'body'   => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'outputFields'        => [
					'commentId' => [ 'type' => 'Int' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					$comment_id = CommunityRepository::create_comment(
						get_current_user_id(),
						(int) $input['postId'],
						(string) $input['body']
					);

					return [ 'commentId' => $comment_id ];
				},
			]
		);

		register_graphql_mutation(
			'updateProfile',
			[
				'inputFields'         => [
					'displayName'   => [ 'type' => 'String' ],
					'homeCity'      => [ 'type' => 'String' ],
					'bio'           => [ 'type' => 'String' ],
					'locationLabel' => [ 'type' => 'String' ],
				],
				'outputFields'        => [
					'profile' => [ 'type' => 'EatForeignPassport' ],
				],
				'mutateAndGetPayload' => static function ( array $input ): array {
					self::require_user();
					return [
						'profile' => CommunityRepository::update_profile( get_current_user_id(), $input ),
					];
				},
			]
		);
	}

	private static function require_user(): void {
		if ( get_current_user_id() <= 0 ) {
			throw new \GraphQL\Error\UserError( 'Authentication required.' );
		}
	}
}
