<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use Rodokmen\Site\Data\Model\Story;

/** Stories exported by the skill (md/pribeh.md) with their front matter. */
final readonly class Stories
{
	public function __construct(private Repository $repository)
	{
	}

	/** The story of the main line (data/pribeh.md of the skill), null when there is none. */
	public function main(): ?Story
	{
		$config = $this->repository->siteConfig();
		$text = $config->hasStory ? $this->repository->markdown('pribeh') : null;

		return $text !== null ? Story::fromMarkdown($text, $this->repository->lineByNumber($config->story->line)) : null;
	}
}
