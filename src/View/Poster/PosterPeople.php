<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\FanChart;

/** What the poster layouts share about the ancestors: which places they draw and how a person is coloured. */
final readonly class PosterPeople
{
	public function __construct(private Repository $repository)
	{
	}

	/**
	 * Places to draw: only where the child is known – an empty place (null) means "parent not found yet".
	 *
	 * @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first
	 * @return array<int, array<int, ?Person>> generation → index in the generation → ancestor
	 */
	public function places(array $generations): array
	{
		$places = [];
		foreach ($generations as $g => $row) {
			foreach ($row as $i => $slug) {
				if ($g === 0 || $generations[$g - 1][intdiv($i, 2)] !== null) {
					$places[$g][$i] = $this->repository->person($slug);
				}
			}
		}

		return $places;
	}

	/** Colour of the i-th ancestor of generation g (g ≥ 1): the family line's, or the father's / mother's side. */
	public function color(Person $person, int $g, int $i): string
	{
		return $person->line !== null
			? $this->repository->lineColor($person->line)
			: FanChart::SideColors[($i >> $g - 1) & 1];
	}

	/** Not found in the registers yet: drawn as an empty place with the name in grey. */
	public function isUnknown(Person $person): bool
	{
		return $person->st === 'unk';
	}

	/** Only probable or uncertain: paler colour, name in italics. */
	public function isUncertain(Person $person): bool
	{
		return $person->st === 'prob' || $person->st === 'mh';
	}

	/** CSS class of the person's name and years (with a leading space, or empty). */
	public function textClass(Person $person): string
	{
		return match (true) {
			$this->isUnknown($person) => ' pst-unk',
			$this->isUncertain($person) => ' pst-prob',
			default => '',
		};
	}
}
