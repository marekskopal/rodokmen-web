<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

use DateTimeImmutable;
use Rodokmen\Site\Data\Repository;

/**
 * The family tree as GEDCOM 5.5.1 (UTF-8, lineage-linked), so that it can be moved to other genealogy software:
 * every ancestor (INDI), the families of their parents (FAM) with the full siblings, every register entry as a
 * source (SOUR) cited by the people and families, and what does not fit a GEDCOM event as notes.
 */
final readonly class Gedcom
{
	public function __construct(private Repository $repository, private GedcomPeople $people, private GedcomFamilies $families)
	{
	}

	public function export(DateTimeImmutable $now): string
	{
		$ids = new GedcomIds($this->repository);
		$out = new GedcomWriter();
		$this->header($out, $now);
		foreach ($this->repository->people() as $person) {
			$this->people->person($out, $ids, $person);
		}
		foreach ($this->repository->families() as $family) {
			$this->people->siblings($out, $ids, $family);
		}
		foreach ($this->repository->families() as $family) {
			$this->families->family($out, $ids, $family);
		}
		foreach ($this->repository->records() as $record) {
			$this->families->source($out, $ids, $record);
		}
		$out->line(0, 'TRLR');

		return $out->toString();
	}

	private function header(GedcomWriter $out, DateTimeImmutable $now): void
	{
		$out->line(0, 'HEAD');
		$out->line(1, 'SOUR', 'RODOKMEN-WEB');
		$out->line(2, 'NAME', 'rodokmen-web');
		$out->line(1, 'DATE', GedcomDate::of($now));
		$out->pointer(1, 'SUBM', 'U1');
		$out->line(1, 'FILE', 'rodokmen.ged');
		$out->line(1, 'GEDC');
		$out->line(2, 'VERS', '5.5.1');
		$out->line(2, 'FORM', 'LINEAGE-LINKED');
		$out->line(1, 'CHAR', 'UTF-8');
		$out->line(1, 'LANG', 'Czech');
		$out->record('U1', 'SUBM');
		$out->line(1, 'NAME', $this->repository->title());
	}
}
