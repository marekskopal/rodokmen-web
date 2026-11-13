<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Relative outside the direct line (data/osoby.py SIDE): a sibling or another relative. */
final readonly class Side
{
	public function __construct(
		public string $name,
		public string $text,
		/** @var list<string> record keys */
		public array $records,
		public string $rel,
		/** Birth year for ordering, may be empty. */
		public string $birthYear,
		public bool $unsure,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('name'),
			$r->string('text', ''),
			$r->stringList('records'),
			$r->string('rel', ''),
			$r->string('y', ''),
			$r->bool('unsure', false),
		);
	}

	/** Full or half sibling. */
	public function isSibling(): bool
	{
		return str_contains($this->rel, 'sourozenec');
	}

	/** Sibling with both parents in common, so also a child of the person's parents. */
	public function isFullSibling(): bool
	{
		return $this->rel === 'sourozenec';
	}

	public function isHalfSibling(): bool
	{
		return str_starts_with($this->rel, 'nevlastní');
	}
}
