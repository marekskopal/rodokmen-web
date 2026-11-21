<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Rodokmen\Site\Data\Model\Line;
use Rodokmen\Site\Data\Model\Person;

/** A family line with what the home page card and the line page show about it. */
final readonly class LineSummary
{
	public function __construct(
		public Line $line,
		/** The line's oldest ancestor in the direct line to the home person (the root of its ancestor tree). */
		public ?Person $root,
		public int $count,
		public int $generations,
		/** The most distant known ancestor. */
		public ?Person $oldest,
		/** @var list<string> places of the line's people, in the order of the people */
		public array $places,
	) {
	}

	/** Paternal lines are 8–11 (the father's grandparents), maternal 12–15. */
	public function isPaternal(): bool
	{
		return $this->line->k <= 11;
	}
}
