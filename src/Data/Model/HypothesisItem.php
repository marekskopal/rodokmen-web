<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** One person in a hypothesis. */
final readonly class HypothesisItem
{
	public function __construct(public string $name, public string $text,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('name'),
			$r->string('text', ''),
		);
	}
}
