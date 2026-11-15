<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

/** One generation of the numbered list of ancestors; generation 0 is the person, 1 the parents. */
final readonly class AncestorGeneration
{
	public function __construct(public int $level, /** @var non-empty-list<AncestorEntry> */ public array $entries,)
	{
	}

	public function title(): string
	{
		return match ($this->level) {
			0 => 'Osoba',
			1 => 'Rodiče',
			2 => 'Prarodiče',
			3 => 'Praprarodiče',
			default => $this->level . '. generace předků',
		};
	}
}
