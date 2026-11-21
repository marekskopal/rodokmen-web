<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Website settings from data/web.json (only what the PHP site uses). */
final readonly class SiteConfig
{
	public function __construct(
		public string $title,
		public string $description,
		public string $footer,
		/** Whom to write to, in the dative as used in a sentence ("Janu Novákovi"). */
		public string $contactName = '',
		public string $contactEmail = '',
		public StoryConfig $story = new StoryConfig(),
		/** Whether the project has a story (data/pribeh.md); the exporter writes a placeholder pribeh.md without it. */
		public bool $hasStory = false,
		public Intro $intro = new Intro(),
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('nazev', 'Rodokmen'),
			$r->string('popis', ''),
			$r->string('zapati', ''),
			$r->object('kontakt')->string('jmeno', ''),
			$r->object('kontakt')->string('email', ''),
			StoryConfig::fromReader($r->object('pribeh')),
			// Older exports without the flag: whether md/pribeh.md exists decides.
			$r->bool('maPribeh', true),
			Intro::fromReader($r->object('uvod')),
		);
	}
}
