<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Teaser of the family story on the home page from data/web.json → "uvod" (all optional). */
final readonly class Intro
{
	public function __construct(
		public string $heading = '',
		/** @var list<string> */
		public array $paragraphs = [],
		/** Image key of a photo in img/ (the exporter copies it there). */
		public string $photo = '',
		public string $photoCaption = '',
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self($r->string('nadpis', ''), $r->stringList('odstavce'), $r->string('foto', ''), $r->string('foto_popis', ''));
	}

	public function hasText(): bool
	{
		return $this->heading !== '' || $this->paragraphs !== [];
	}
}
