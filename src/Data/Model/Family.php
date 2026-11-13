<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Parents of an ancestor and their children known in the tree. */
final readonly class Family
{
	public function __construct(
		public string $id,
		public ?string $father,
		public ?string $mother,
		public bool $fatherUnknown,
		public string $child,
		/** @var list<string> */
		public array $children,
		/** @var list<string> marriage facts */
		public array $marriage,
		/** @var list<string> record keys */
		public array $records,
		public ?int $line,
		public int $gen,
		/** @var list<Side> relatives of the children */
		public array $side,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('id'),
			$r->nullableString('father'),
			$r->nullableString('mother'),
			$r->bool('fatherUnknown', false),
			$r->string('child'),
			$r->stringList('children'),
			$r->stringList('marriage'),
			$r->stringList('records'),
			$r->nullableInt('line'),
			$r->int('gen', 0),
			array_map(Side::fromReader(...), $r->list('side')),
		);
	}

	/**
	 * Children's full siblings who are not in the direct line (half siblings have another parent).
	 *
	 * @return list<Side>
	 */
	public function otherChildren(): array
	{
		return array_values(array_filter($this->side, static fn (Side $s): bool => $s->isFullSibling()));
	}
}
