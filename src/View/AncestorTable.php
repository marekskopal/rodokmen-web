<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;

/**
 * Numbered list of a person's ancestors (vývod) for printing. The numbers count from the chosen person (Kekulé):
 * 1 = the person, 2n = father of n, 2n + 1 = mother of n, so a gap in the numbers is an unknown ancestor.
 * An ancestor met again is listed with a reference to the first number and their ancestors are not repeated,
 * which also ends the walk on a cycle in the data.
 */
final readonly class AncestorTable
{
	/** More generations than any parish register reaches. */
	public const MaxDepth = 30;

	public function __construct(private Repository $repository)
	{
	}

	/** Number of generations of known ancestors above the person. */
	public function availableDepth(Person $person): int
	{
		return count($this->build($person, self::MaxDepth)) - 1;
	}

	/** @return non-empty-list<AncestorGeneration> generations 0 … $depth that have someone known */
	public function build(Person $person, int $depth): array
	{
		$row = [new AncestorEntry(1, $person)];
		$generations = [new AncestorGeneration(0, $row)];
		$listed = [$person->slug => 1];
		$depth = max(0, min($depth, self::MaxDepth));
		for ($level = 1; $level <= $depth; $level++) {
			$next = [];
			foreach ($row as $child) {
				if ($child->sameAs !== null) {
					continue;
				}

				$parents = [$child->fatherNumber() => $child->person->father, $child->motherNumber() => $child->person->mother];
				foreach ($parents as $number => $slug) {
					$ancestor = $this->repository->person($slug);
					if ($ancestor === null) {
						continue;
					}

					$next[] = new AncestorEntry($number, $ancestor, $listed[$ancestor->slug] ?? null);
					$listed[$ancestor->slug] ??= $number;
				}
			}

			if ($next === []) {
				break;
			}

			$generations[] = new AncestorGeneration($level, $next);
			$row = $next;
		}

		return $generations;
	}
}
