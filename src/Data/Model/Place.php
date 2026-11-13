<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Village or town where ancestors lived (map). */
final readonly class Place
{
	public function __construct(
		public string $name,
		public float $lat,
		public float $lon,
		/** @var list<PlaceLine> */
		public array $lines,
		/** @var list<int> first and last year */
		public array $years,
		public bool $hypothesis,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('name'),
			$r->float('lat'),
			$r->float('lon'),
			array_map(PlaceLine::fromReader(...), $r->list('lines')),
			$r->has('years') ? $r->intList('years') : [],
			$r->bool('hypothesis', false),
		);
	}
}
