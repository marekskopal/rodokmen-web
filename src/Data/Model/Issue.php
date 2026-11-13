<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Open research question (data/osoby.py ISSUES). */
final readonly class Issue
{
	public function __construct(public string $title, public string $text,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('title'),
			$r->string('text', ''),
		);
	}

	public function isSolved(): bool
	{
		return str_starts_with($this->text, 'Vyřešeno');
	}
}
