<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Family line (rod) rooted in one of the default person's great-grandparents. */
final readonly class Line
{
	public function __construct(
		public int $k,
		public string $slug,
		public string $name,
		public string $desc,
		public string $color,
		public string $root,
		/** @var list<string> */
		public array $people,
		/** @var array<string, int> generation by person slug */
		public array $gens,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->int('k'),
			$r->string('slug'),
			$r->string('name'),
			$r->string('desc', ''),
			$r->string('color'),
			$r->string('root'),
			$r->stringList('people'),
			$r->intMap('gens'),
		);
	}

	public function generationCount(): int
	{
		return $this->gens !== [] ? max($this->gens) - min($this->gens) + 1 : 0;
	}
}
