<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Family photo or document from the family albums. */
final readonly class Document
{
	public function __construct(public string $img, public string $caption, /** @var list<string> person slugs */ public array $people,)
	{
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('img'),
			$r->string('caption', ''),
			$r->stringList('people'),
		);
	}
}
