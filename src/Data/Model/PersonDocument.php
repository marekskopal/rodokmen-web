<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Family photo or document attached to a person. */
final readonly class PersonDocument
{
	public function __construct(public string $img, public string $caption,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('img'),
			$r->string('caption', ''),
		);
	}
}
