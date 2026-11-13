<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Unproven link in the tree (data/osoby.py HYPOTEZY). */
final readonly class Hypothesis
{
	public function __construct(
		public int $line,
		public string $title,
		public string $text,
		/** @var list<HypothesisItem> */
		public array $items,
		/** @var list<string> record keys */
		public array $records,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->int('line', 0),
			$r->string('title'),
			$r->string('text', ''),
			array_map(HypothesisItem::fromReader(...), $r->list('items')),
			$r->stringList('records'),
		);
	}
}
