<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

/** A life event for GEDCOM (BIRT, CHR, MARR, DEAT) parsed from a fact. */
final readonly class GedcomEvent
{
	public function __construct(
		public string $tag,
		public ?string $date,
		public string $place,
		/** The fact as written, when the date and place do not hold all of it; empty otherwise. */
		public string $note,
	) {
	}
}
