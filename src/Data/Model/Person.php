<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Ancestor of the default person (one Kekulé number of data/osoby.py). */
final readonly class Person
{
	public function __construct(
		public int $k,
		public string $slug,
		public string $name,
		/** Lifespan as written, e.g. "1746 – 1801". */
		public string $y,
		public string $years,
		/** Evidence: doc, prob, fam, mh or unk. */
		public string $st,
		public string $status,
		public int $gen,
		/** M or F. */
		public string $sex,
		/** Relation word from the default person. */
		public string $rel,
		public ?int $line,
		/** @var list<string> */
		public array $facts,
		public string $note,
		/** @var list<string> record keys */
		public array $records,
		public ?string $father,
		public ?string $mother,
		public bool $fatherUnknown,
		public bool $motherUnknown,
		public ?string $child,
		public ?string $spouse,
		public ?string $family,
		public ?string $ownFamily,
		/** @var list<string> slugs down to the tree root */
		public array $path,
		/** @var list<Side> */
		public array $side,
		/** @var list<PersonDocument> */
		public array $docs,
		/** @var list<string> */
		public array $children,
		/** @var list<Alias> */
		public array $also,
		public ?string $lineSlug,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->int('k'),
			$r->string('slug'),
			$r->string('name'),
			$r->string('y', ''),
			$r->string('years', ''),
			$r->string('st', 'unk'),
			$r->string('status', ''),
			$r->int('gen', 0),
			$r->string('sex', 'M'),
			$r->string('rel', ''),
			$r->nullableInt('line'),
			$r->stringList('facts'),
			$r->string('note', ''),
			$r->stringList('records'),
			$r->nullableString('father'),
			$r->nullableString('mother'),
			$r->bool('fatherUnknown', false),
			$r->bool('motherUnknown', false),
			$r->nullableString('child'),
			$r->nullableString('spouse'),
			$r->nullableString('family'),
			$r->nullableString('ownFamily'),
			$r->stringList('path'),
			array_map(Side::fromReader(...), $r->list('side')),
			array_map(PersonDocument::fromReader(...), $r->list('docs')),
			$r->stringList('children'),
			array_map(Alias::fromReader(...), $r->list('also')),
			$r->nullableString('lineSlug'),
		);
	}

	public function isFemale(): bool
	{
		return $this->sex === 'F';
	}

	/** First four-digit year of the lifespan (birth), or null. */
	public function birthYear(): ?int
	{
		return preg_match('/\d{4}/', $this->y, $m) === 1 ? (int) $m[0] : null;
	}

	/** @return list<Side> */
	public function siblings(): array
	{
		return array_values(array_filter($this->side, static fn (Side $s): bool => $s->isSibling()));
	}

	/** @return list<Side> */
	public function otherRelatives(): array
	{
		return array_values(array_filter($this->side, static fn (Side $s): bool => !$s->isSibling()));
	}
}
