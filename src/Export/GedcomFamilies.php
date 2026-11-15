<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

use Rodokmen\Site\Data\Model\Family;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Model\Record;
use Rodokmen\Site\Data\Repository;

/** FAM records (the parents of every ancestor and their children) and SOUR records (the register entries). */
final readonly class GedcomFamilies
{
	public function __construct(private Repository $repository, private GedcomBlocks $blocks)
	{
	}

	public function family(GedcomWriter $out, GedcomIds $ids, Family $family): void
	{
		$out->record($ids->family($family->id), 'FAM');
		// The people are looked up, so that an alias in the family leads to the person's record.
		foreach (['HUSB' => $family->father, 'WIFE' => $family->mother] as $tag => $slug) {
			$parent = $this->repository->person($slug);
			if ($parent !== null) {
				$out->pointer(1, $tag, $ids->person($parent->slug));
			}
		}
		foreach ($family->children as $slug) {
			$child = $this->repository->person($slug);
			if ($child !== null) {
				$out->pointer(1, 'CHIL', $ids->person($child->slug));
			}
		}
		foreach (array_keys($family->otherChildren()) as $index) {
			$out->pointer(1, 'CHIL', $ids->sibling($family->id, $index));
		}

		$this->marriage($out, $family);
		$this->blocks->citations($out, $ids, $family->records);
	}

	/**
	 * The first marriage fact is the marriage (its partner's name is not part of the place); the rest are notes.
	 * Without the family's marriage facts, those of the couple are used, as the skill's exporter does.
	 */
	private function marriage(GedcomWriter $out, Family $family): void
	{
		$father = $this->repository->person($family->father);
		$mother = $this->repository->person($family->mother);
		$partners = self::partnerNames($mother, $father);
		foreach ($this->blocks->marriageFacts($family) as $index => $fact) {
			if ($index > 0) {
				$this->blocks->notes($out, [$fact]);

				continue;
			}

			$event = FactParser::event($fact) ?? new GedcomEvent('MARR', null, '', $fact);
			foreach ($partners as $partner) {
				$withoutPartner = FactParser::event($fact, $partner);
				if ($withoutPartner !== null && $withoutPartner->place !== $event->place) {
					$event = $withoutPartner;

					break;
				}
			}
			$this->blocks->event($out, $event);
		}
	}

	/**
	 * Names the partners may have in a marriage fact: as in the tree, and given names with the birth surname
	 * ("Marie Nováková roz. Malá" → also "Marie Malá").
	 *
	 * @return list<string>
	 */
	private static function partnerNames(?Person ...$people): array
	{
		$names = [];
		foreach ($people as $person) {
			if ($person !== null) {
				[$given, $surname] = FactParser::name($person->name);
				$names[] = $person->name;
				$names[] = trim($given . ' ' . $surname);
			}
		}

		return array_values(array_unique($names));
	}

	public function source(GedcomWriter $out, GedcomIds $ids, Record $record): void
	{
		$out->record($ids->source($record->key), 'SOUR');
		$out->line(1, 'TITL', $record->title . ($record->date !== '' ? ', ' . $record->date : ''));
		if ($record->archive !== '') {
			$out->line(1, 'AUTH', $record->archive);
		}
		if ($record->portal !== '') {
			$out->line(1, 'PUBL', $record->portal);
		}
		$volume = $record->volume !== '' && $record->volume !== $record->vdesc ? ' (' . $record->volume . ')' : '';
		$this->blocks->notes($out, [trim($record->vdesc . $volume), $record->summary, $record->url]);
	}
}
