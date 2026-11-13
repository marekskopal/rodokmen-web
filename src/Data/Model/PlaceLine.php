<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** People of one family line who lived in a place (k = 0: the closest family). */
final readonly class PlaceLine
{
	public function __construct(public int $k, /** @var list<string> */ public array $people,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->int('k'),
			$r->stringList('people'),
		);
	}
}
