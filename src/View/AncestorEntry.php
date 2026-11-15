<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Rodokmen\Site\Data\Model\Person;

/** One ancestor in the numbered list of ancestors (vývod): 1 = the person, 2n = father of n, 2n + 1 = mother of n. */
final readonly class AncestorEntry
{
	public function __construct(
		public int $number,
		public Person $person,
		/** Number under which the same person is already listed (the tree meets itself), or null. */
		public ?int $sameAs = null,
	) {
	}

	public function fatherNumber(): int
	{
		return 2 * $this->number;
	}

	public function motherNumber(): int
	{
		return 2 * $this->number + 1;
	}
}
