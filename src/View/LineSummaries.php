<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Rodokmen\Site\Data\Model\Line;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;

/** Summaries of the family lines for the home page cards and the line pages. */
final readonly class LineSummaries
{
	public function __construct(private Repository $repository)
	{
	}

	/** @return list<LineSummary> in the order of site.json */
	public function all(): array
	{
		return array_values(array_map($this->of(...), $this->repository->lines()));
	}

	public function of(Line $line): LineSummary
	{
		$oldest = null;
		$places = [];
		foreach ($this->people($line) as $person) {
			if ($oldest === null || $person->gen > $oldest->gen) {
				$oldest = $person;
			}
			foreach ($this->repository->placeNamesOf($person->slug) as $place) {
				$places[$place] = true;
			}
		}

		return new LineSummary(
			$line,
			$this->repository->person($line->root),
			count($line->people),
			$line->generationCount(),
			$oldest,
			array_map(strval(...), array_keys($places)),
		);
	}

	/**
	 * People of the line by generation, the most distant first.
	 *
	 * @return array<int, list<Person>>
	 */
	public function generations(Line $line): array
	{
		$byGeneration = [];
		foreach ($this->people($line) as $person) {
			$byGeneration[$person->gen][] = $person;
		}
		krsort($byGeneration);
		foreach ($byGeneration as &$people) {
			usort($people, static fn (Person $a, Person $b): int => $a->k <=> $b->k);
		}
		unset($people);

		return $byGeneration;
	}

	/** @return list<Person> */
	private function people(Line $line): array
	{
		return array_values(array_filter(array_map($this->repository->person(...), $line->people)));
	}
}
