<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Another Kekulé number of the same person (pedigree collapse). */
final readonly class Alias
{
	public function __construct(public int $k, public string $rel, public ?int $line, public ?string $child,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->int('k'),
			$r->string('rel', ''),
			$r->nullableInt('line'),
			$r->nullableString('child'),
		);
	}
}
