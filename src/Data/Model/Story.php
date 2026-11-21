<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\FrontMatter;

/** A story of a family line written in markdown with a front matter (title, lead), e.g. data/pribeh.md of the skill. */
final readonly class Story
{
	public function __construct(
		public string $title,
		public string $lead,
		/** Markdown without the front matter. */
		public string $text,
		/** The line the story is about. */
		public ?Line $line,
		/** The main story (data/pribeh.md), not one of the other lines (data/pribehy/<line>.md). */
		public bool $main = false,
	) {
	}

	/** The main story without a known line stays at /pribeh. */
	public function url(): string
	{
		return $this->line !== null ? '/pribeh/' . rawurlencode($this->line->slug) : '/pribeh';
	}

	public static function fromMarkdown(string $markdown, ?Line $line, bool $main = false): self
	{
		$document = FrontMatter::parse($markdown);
		$title = $document->field('title');
		$body = $document->body;
		// Without a front matter the title is the first heading (older stories, "# Příběh rodu").
		if ($title === '' && preg_match('/\A#[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*(?:\R|\z)/', $body, $m) === 1) {
			$title = $m[1];
			$body = ltrim(substr($body, strlen($m[0])), "\r\n");
		}

		return new self($title !== '' ? $title : 'Příběh rodu', $document->field('lead'), $body, $line, $main);
	}
}
