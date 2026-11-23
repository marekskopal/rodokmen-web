<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Rodokmen\Site\Data\Model\Line;

/** What the fan chart draws, for its legend: only the keys that appear in the chart are listed. */
final readonly class FanLegend
{
	public function __construct(
		/** @var list<Line> the family lines with at least one drawn ancestor, in the order of the data */
		public array $lines,
		/** whether ancestors outside every family line are drawn (in the colours of the father's and mother's side) */
		public bool $sides,
		/** whether probable ancestors or ancestors only from someone else's tree are drawn (hatched) */
		public bool $uncertain,
		/** whether an ancestor's slot is empty (not found yet) or holds a person of unknown identity */
		public bool $unknown,
	) {
	}
}
