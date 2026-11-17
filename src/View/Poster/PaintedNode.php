<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;

/** One ancestor of the painted tree: where the name plaque sits and how thick the wood below it is. */
final readonly class PaintedNode
{
	public function __construct(
		public Person $person,
		/** Generation (0 = the person of the poster) and index within it. */
		public int $g,
		public int $i,
		/** Centre of the plaque. */
		public float $x,
		public float $y,
		/** Size of the plaque. */
		public float $w,
		public float $h,
		/** Thickness of the wood where it reaches this ancestor: it carries them and everybody above (pipe model). */
		public float $girth,
		/** No known parents: a tip of the tree. */
		public bool $tip,
		/** The name is on a ribbon (banderole); otherwise on an oval, where the place is too narrow for one. */
		public bool $ribbon,
	) {
	}

	/** Whether the point lies on the plaque, enlarged $grow times. */
	public function covers(float $x, float $y, float $grow = 1.0): bool
	{
		if ($this->ribbon) {
			// The tails of the ribbon hang a little below its body.
			return abs($x - $this->x) <= $this->w / 2 * $grow && abs($y - $this->y - $this->h * .1) <= $this->h * .6 * $grow;
		}

		return (($x - $this->x) / ($this->w / 2 * $grow)) ** 2 + (($y - $this->y) / ($this->h / 2 * $grow)) ** 2 <= 1;
	}
}
