<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** The drawn poster: the SVG of the whole sheet, its generations and the size of its smallest letters (mm). */
final readonly class PosterSheet
{
	public function __construct(public string $svg, public int $depth, public float $smallestFont)
	{
	}

	/** Smallest font size in typographic points (the SVG is drawn in millimetres). */
	public function smallestPoints(): float
	{
		return $this->smallestFont * 72 / 25.4;
	}
}
