<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Totals shown on the home page. */
final readonly class Stats
{
	public function __construct(public int $ancestors, public int $documented, public int $records, public int $generations,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->int('ancestors', 0),
			$r->int('documented', 0),
			$r->int('records', 0),
			$r->int('generations', 0),
		);
	}
}
