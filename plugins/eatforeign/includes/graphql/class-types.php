<?php
/**
 * GraphQL object types.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\GraphQL;

final class Types {
	public static function register(): void {
		register_graphql_object_type(
			'EatForeignCelebrationStats',
			[
				'description' => 'Celebration engagement summary',
				'fields'      => [
					'postsCount'        => [ 'type' => 'Int' ],
					'completionsCount'  => [ 'type' => 'Int' ],
					'averageRating'     => [ 'type' => 'Float' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignCelebration',
			[
				'description' => 'EatForeign celebration',
				'fields'      => [
					'id'                 => [ 'type' => 'Int' ],
					'slug'               => [ 'type' => 'String' ],
					'title'              => [ 'type' => 'String' ],
					'eventDate'          => [ 'type' => 'String' ],
					'recurringRule'      => [ 'type' => 'String' ],
					'shortDescription'   => [ 'type' => 'String' ],
					'longDescription'    => [ 'type' => 'String' ],
					'country'            => [ 'type' => 'String' ],
					'countrySlug'        => [ 'type' => 'String' ],
					'celebrationType'    => [ 'type' => 'String' ],
					'heroImage'          => [ 'type' => 'String' ],
					'featuredDishSlugs'  => [ 'type' => [ 'list_of' => 'String' ] ],
					'stats'              => [ 'type' => 'EatForeignCelebrationStats' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignImageAttribution',
			[
				'description' => 'Photo credit and license for a remote or generated image',
				'fields'      => [
					'url'           => [ 'type' => 'String' ],
					'sourceType'    => [ 'type' => 'String' ],
					'sourceName'    => [ 'type' => 'String' ],
					'author'        => [ 'type' => 'String' ],
					'license'       => [ 'type' => 'String' ],
					'licenseUrl'    => [ 'type' => 'String' ],
					'creditPageUrl' => [ 'type' => 'String' ],
					'creditLine'    => [ 'type' => 'String' ],
					'caption'       => [ 'type' => 'String' ],
					'isAiGenerated' => [ 'type' => 'Boolean' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignDish',
			[
				'description' => 'EatForeign dish',
				'fields'      => [
					'id'                       => [ 'type' => 'Int' ],
					'slug'                     => [ 'type' => 'String' ],
					'title'                    => [ 'type' => 'String' ],
					'description'              => [ 'type' => 'String' ],
					'originCountry'            => [ 'type' => 'String' ],
					'countrySlug'              => [ 'type' => 'String' ],
					'cuisineType'              => [ 'type' => 'String' ],
					'dishType'                 => [ 'type' => 'String' ],
					'spiceLevel'               => [ 'type' => 'String' ],
					'culturalMeaning'          => [ 'type' => 'String' ],
					'averageRating'            => [ 'type' => 'Float' ],
					'ingredients'              => [ 'type' => [ 'list_of' => 'String' ] ],
					'gallery'                  => [ 'type' => [ 'list_of' => 'String' ] ],
					'heroImage'                => [ 'type' => 'String' ],
					'featuredImageAttribution' => [ 'type' => 'EatForeignImageAttribution' ],
					'imageAttributions'        => [ 'type' => [ 'list_of' => 'EatForeignImageAttribution' ] ],
					'celebrationSlugs'         => [ 'type' => [ 'list_of' => 'String' ] ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignPassportPhoto',
			[
				'description' => 'Passport photo with optional caption',
				'fields'      => [
					'url'     => [ 'type' => 'String' ],
					'caption' => [ 'type' => 'String' ],
				],
			]
		);

		register_graphql_input_type(
			'EatForeignPassportPhotoInput',
			[
				'fields' => [
					'url'     => [ 'type' => 'String' ],
					'caption' => [ 'type' => 'String' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignPassportEntry',
			[
				'description' => 'Food passport dish entry',
				'fields'      => [
					'dishId'          => [ 'type' => 'Int' ],
					'dishSlug'        => [ 'type' => 'String' ],
					'dishTitle'       => [ 'type' => 'String' ],
					'rating'          => [ 'type' => 'Float' ],
					'triedOn'         => [ 'type' => 'String' ],
					'note'            => [ 'type' => 'String' ],
					'photos'          => [ 'type' => [ 'list_of' => 'EatForeignPassportPhoto' ] ],
					'imageUrl'        => [ 'type' => 'String' ],
					'postId'          => [ 'type' => 'Int' ],
					'restaurantName'  => [ 'type' => 'String' ],
					'firstTimeTrying' => [ 'type' => 'Boolean' ],
					'celebrationId'   => [ 'type' => 'Int' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignDishPassportPhoto',
			[
				'description' => 'Community passport photo on a dish page',
				'fields'      => [
					'url'               => [ 'type' => 'String' ],
					'caption'           => [ 'type' => 'String' ],
					'authorDisplayName' => [ 'type' => 'String' ],
					'authorSlug'        => [ 'type' => 'String' ],
					'authorPassportUrl' => [ 'type' => 'String' ],
					'triedOn'           => [ 'type' => 'String' ],
					'rating'            => [ 'type' => 'Float' ],
					'postId'            => [ 'type' => 'Int' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignPassport',
			[
				'description' => 'EatForeign food passport',
				'fields'      => [
					'slug'                  => [ 'type' => 'String' ],
					'displayName'           => [ 'type' => 'String' ],
					'homeCity'              => [ 'type' => 'String' ],
					'bio'                   => [ 'type' => 'String' ],
					'countriesExplored'     => [ 'type' => 'Int' ],
					'dishesTried'           => [ 'type' => 'Int' ],
					'celebrationsCompleted' => [ 'type' => 'Int' ],
					'entries'               => [ 'type' => [ 'list_of' => 'EatForeignPassportEntry' ] ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignComment',
			[
				'description' => 'Comment on a celebration post',
				'fields'      => [
					'id'        => [ 'type' => 'Int' ],
					'author'    => [ 'type' => 'String' ],
					'body'      => [ 'type' => 'String' ],
					'createdAt' => [ 'type' => 'String' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignCelebrationPost',
			[
				'description' => 'User celebration post',
				'fields'      => [
					'id'              => [ 'type' => 'Int' ],
					'userDisplayName' => [ 'type' => 'String' ],
					'celebrationSlug' => [ 'type' => 'String' ],
					'dishSlug'        => [ 'type' => 'String' ],
					'caption'         => [ 'type' => 'String' ],
					'rating'          => [ 'type' => 'Float' ],
					'imageUrl'        => [ 'type' => 'String' ],
					'restaurantName'  => [ 'type' => 'String' ],
					'firstTimeTrying' => [ 'type' => 'Boolean' ],
					'likesCount'      => [ 'type' => 'Int' ],
					'comments'        => [ 'type' => [ 'list_of' => 'EatForeignComment' ] ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignNearbyRestaurant',
			[
				'description' => 'Restaurant from Google Places',
				'fields'      => [
					'id'      => [ 'type' => 'String' ],
					'name'    => [ 'type' => 'String' ],
					'address' => [ 'type' => 'String' ],
					'lat'     => [ 'type' => 'Float' ],
					'lng'     => [ 'type' => 'Float' ],
					'website' => [ 'type' => 'String' ],
					'rating'  => [ 'type' => 'Float' ],
				],
			]
		);

		register_graphql_object_type(
			'EatForeignCountry',
			[
				'description' => 'EatForeign country hub',
				'fields'      => [
					'name'              => [ 'type' => 'String' ],
					'slug'              => [ 'type' => 'String' ],
					'overview'          => [ 'type' => 'String' ],
					'heroImage'         => [ 'type' => 'String' ],
					'dishSlugs'         => [ 'type' => [ 'list_of' => 'String' ] ],
					'celebrationSlugs'  => [ 'type' => [ 'list_of' => 'String' ] ],
				],
			]
		);
	}
}
