<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use Rodokmen\Site\Data\Model\Story;

/**
 * Stories of the family lines with their front matter: the main story exported by the skill (md/pribeh.md, its line
 * is web.json → pribeh.rod) and the stories of the other lines published by rodokmen-sync (md/pribeh-<line>.md).
 */
final class Stories
{
	/** @var array<string, Story>|null by line slug, '' for a main story whose line is not in site.json */
	private ?array $stories = null;

	public function __construct(private readonly Repository $repository)
	{
	}

	/** The story of the main line (data/pribeh.md of the skill), null when there is none. */
	public function main(): ?Story
	{
		foreach ($this->stories() as $story) {
			if ($story->main) {
				return $story;
			}
		}

		return null;
	}

	public function forLine(string $slug): ?Story
	{
		return $slug !== '' ? ($this->stories()[$slug] ?? null) : null;
	}

	/** @return list<Story> a main story without a line first, then in the order of the lines in site.json */
	public function all(): array
	{
		return array_values($this->stories());
	}

	/**
	 * Menu item of the stories: the story itself when there is one (labelled web.json → pribeh.menu when it is the
	 * main one), the list of stories when there are more, null when there is none.
	 *
	 * @return array{string, string}|null [address, label]
	 */
	public function menu(): ?array
	{
		$stories = $this->all();
		if (count($stories) !== 1) {
			return $stories !== [] ? ['/pribehy', 'Příběhy'] : null;
		}

		$menu = $this->repository->siteConfig()->story->menu;

		return [$stories[0]->url(), $stories[0]->main && $menu !== '' ? $menu : 'Příběh rodu'];
	}

	/** @return array<string, Story> */
	private function stories(): array
	{
		if ($this->stories !== null) {
			return $this->stories;
		}

		$main = $this->loadMain();
		$stories = $main !== null && $main->line === null ? ['' => $main] : [];
		foreach ($this->repository->lines() as $slug => $line) {
			if ($main?->line?->slug === $slug) {
				$stories[$slug] = $main;

				continue;
			}
			$text = $this->repository->markdown('pribeh-' . $slug);
			if ($text !== null) {
				$stories[$slug] = Story::fromMarkdown($text, $line);
			}
		}

		return $this->stories = $stories;
	}

	private function loadMain(): ?Story
	{
		$config = $this->repository->siteConfig();
		$text = $config->hasStory ? $this->repository->markdown('pribeh') : null;

		return $text !== null ? Story::fromMarkdown($text, $this->repository->lineByNumber($config->story->line), main: true) : null;
	}
}
