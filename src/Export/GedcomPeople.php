<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

use Rodokmen\Site\Data\Model\Family;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;

/** INDI records: the ancestors and the full siblings of the ancestors. */
final readonly class GedcomPeople
{
	private const Status = [
		'doc' => 'doloženo matrikou',
		'prob' => 'pravděpodobné',
		'fam' => 'údaj od rodiny',
		'mh' => 'jen z cizího rodokmenu',
		'unk' => 'neznámé',
	];

	public function __construct(private Repository $repository, private GedcomBlocks $blocks)
	{
	}

	public function person(GedcomWriter $out, GedcomIds $ids, Person $person): void
	{
		$out->record($ids->person($person->slug), 'INDI');
		$this->blocks->name($out, $person->name);
		$out->line(1, 'SEX', $person->isFemale() ? 'F' : 'M');

		[$notes, $events] = $this->facts($out, $person);
		[$birth, $death] = FactParser::lifespan($person->y);
		if ($birth !== null && !isset($events['BIRT']) && !isset($events['CHR'])) {
			$this->blocks->event($out, new GedcomEvent('BIRT', $birth, '', ''));
		}
		if ($death !== null && !isset($events['DEAT'])) {
			$this->blocks->event($out, new GedcomEvent('DEAT', $death, '', ''));
		}

		$notes[] = $person->note;
		$status = self::Status[$person->st] ?? $person->st;
		$notes[] = 'Věrohodnost: ' . $status . ($person->status !== '' && $person->status !== $status ? ' (' . $person->status . ')' : '');
		$notes[] = $this->relatives($person);
		$this->blocks->notes($out, $notes);
		$this->blocks->citations($out, $ids, $person->records);

		if ($this->repository->family($person->family) !== null) {
			$out->pointer(1, 'FAMC', $ids->family((string) $person->family));
		}
		foreach ($ids->familiesAsParent($person->slug) as $family) {
			$out->pointer(1, 'FAMS', $family);
		}
	}

	/** The full siblings of an ancestor (children of the same parents) as people of their own. */
	public function siblings(GedcomWriter $out, GedcomIds $ids, Family $family): void
	{
		foreach ($family->otherChildren() as $index => $sibling) {
			$out->record($ids->sibling($family->id, $index), 'INDI');
			$this->blocks->name($out, $sibling->name);
			// The birth year, or the years in the name: "Josef Novák (1865–1920)".
			[$birth, $death] = FactParser::lifespan($sibling->birthYear);
			if ($birth === null && preg_match('/\(([^)]*)\)/u', $sibling->name, $m) === 1) {
				[$birth, $death] = FactParser::lifespan($m[1]);
			}
			foreach (['BIRT' => $birth, 'DEAT' => $death] as $tag => $date) {
				if ($date !== null) {
					$this->blocks->event($out, new GedcomEvent($tag, $date, '', ''));
				}
			}
			$this->blocks->notes($out, [$sibling->text, $sibling->unsure ? 'Příbuzenství není jisté.' : '']);
			$this->blocks->citations($out, $ids, $sibling->records);
			$out->pointer(1, 'FAMC', $ids->family($family->id));
		}
	}

	/**
	 * Writes the events of the facts; returns the facts left for the notes and the tags written.
	 *
	 * @return array{list<string>, array<string, true>}
	 */
	private function facts(GedcomWriter $out, Person $person): array
	{
		$notes = [];
		$events = [];
		$family = $this->repository->family($person->ownFamily);
		foreach ($person->facts as $fact) {
			$event = FactParser::event($fact);
			if ($event === null) {
				$notes[] = $fact;
			} elseif ($event->tag === 'MARR') {
				// A marriage belongs to the couple's family; a note without it or for another marriage.
				if ($family === null || !in_array($fact, $this->blocks->marriageFacts($family), true)) {
					$notes[] = $fact;
				}
			} else {
				$this->blocks->event($out, $event);
				$events[$event->tag] = true;
			}
		}

		return [$notes, $events];
	}

	/** Relatives outside the direct line that are not exported as people of their own. */
	private function relatives(Person $person): string
	{
		$hasFamily = $this->repository->family($person->family) !== null;
		$lines = [];
		foreach ($person->side as $side) {
			if (!$side->isFullSibling() || !$hasFamily) {
				$lines[] = '- ' . $side->name . ($side->rel !== '' ? ', ' . $side->rel : '') . ($side->text !== '' ? ': ' . $side->text : '');
			}
		}

		return $lines === [] ? '' : "Příbuzní:\n" . implode("\n", $lines);
	}
}
