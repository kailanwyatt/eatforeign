<?php
/**
 * Post type constants.
 *
 * @package EatForeign
 */

declare(strict_types=1);

namespace EatForeign\Support;

final class PostType {
	public const CELEBRATION      = 'ef_celebration';
	public const DISH             = 'ef_dish';
	public const COUNTRY          = 'ef_country';
	public const RESTAURANT       = 'ef_restaurant';
	public const CELEBRATION_POST = 'ef_celebration_post';
	public const COMMENT          = 'ef_comment';

	/**
	 * @return list<string>
	 */
	public static function editorial(): array {
		return [
			self::CELEBRATION,
			self::DISH,
			self::COUNTRY,
			self::RESTAURANT,
		];
	}

	/**
	 * @return list<string>
	 */
	public static function community(): array {
		return [
			self::CELEBRATION_POST,
			self::COMMENT,
		];
	}
}
