<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** How the boxes of one generation of the poster tree write their names. */
final readonly class BoxText
{
	public function __construct(
		/** Font size of the names in millimetres; the years are a little smaller on their own line. */
		public float $size,
		/** Name over the years; otherwise both on one line. */
		public bool $twoLines,
		/** Turned by 90° to run along a tall box. */
		public bool $turned,
		/** The name on two lines too: the given names over the surname (oval plaques of the painted tree). */
		public bool $split = false,
	) {
	}
}
