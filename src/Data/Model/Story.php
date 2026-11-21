<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\FrontMatter;

/** A story written in markdown with a front matter (title, lead), e.g. data/pribeh.md of the skill. */
final readonly class Story
{
	public function __construct(
		public string $title,
		public string $lead,
		/** Markdown without the front matter. */
		public string $text,
		/** The line the story is about. */
		public ?Line $line,
	) {
	}

	public static function fromMarkdown(string $markdown, ?Line $line): self
	{
		$document = FrontMatter::parse($markdown);
		$title = $document->field('title');
		$body = $document->body;
		// Without a front matter the title is the first heading (older stories, "# Příběh rodu").
		if ($title === '' && preg_match('/\A#[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*(?:\R|\z)/', $body, $m) === 1) {
			$title = $m[1];
			$body = ltrim(substr($body, strlen($m[0])), "\r\n");
		}

		return new self($title !== '' ? $title : 'Příběh rodu', $document->field('lead'), $body, $line);
	}
}
