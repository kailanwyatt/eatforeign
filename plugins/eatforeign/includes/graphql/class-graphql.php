<?php
/**
 * GraphQL registration entrypoint.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\GraphQL;

final class GraphQL {
	public static function register(): void {
		if (! function_exists( 'register_graphql_object_type' ) ) {
			return;
		}

		Types::register();
		Queries::register();
		Mutations::register();
	}
}
