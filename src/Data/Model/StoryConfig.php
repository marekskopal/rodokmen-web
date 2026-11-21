<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Settings of the family story from data/web.json → "pribeh" (empty texts mean the defaults). */
final readonly class StoryConfig
{
	/** The skill's main line when web.json does not name another one: the home person's paternal great-grandfather. */
	public const DefaultLine = 8;

	public function __construct(
		/** Label of the menu item. */
		public string $menu = '',
		/** Kekulé number of the line the story is about (Line::$k). */
		public int $line = self::DefaultLine,
		/** Label of the button on the home page. */
		public string $button = '',
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		// "rod": null means the default line, like in the skill's website (config.pribeh?.rod ?? 8).
		return new self($r->string('menu', ''), $r->nullableInt('rod') ?? self::DefaultLine, $r->string('tlacitko', ''));
	}
}
