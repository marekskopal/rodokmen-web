<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;

/**
 * Where the ancestors of the painted tree sit: every generation a row above the person's, every ancestor near the
 * middle of the same share of the width as in the upward tree, but a little off at random, so that the rows do not
 * look ruled. Also measures the wood: a branch is as thick as the number of ancestors it carries (pipe model).
 */
final readonly class PaintedLayout
{
	/** Height of the mound relative to the whole height, and its half-width relative to the width. */
	public const GroundHeight = .07;

	public const GroundWidth = .22;

	/** Height of the person's row (with the trunk) and of the room for leaves above the top row, in rows. */
	private const RootRow = 1.6;

	private const CrownRoom = .3;

	/** Oval plaque: height relative to the row; its width relative to its height at most. */
	private const PlaqueHeight = .56;

	private const PlaqueWidth = 2.4;

	/** Ribbon: height relative to the row, width relative to its height at most and at least (else an oval). */
	private const RibbonHeight = .36;

	private const RibbonWidth = 4.6;

	private const RibbonMinWidth = 2.4;

	/** Largest plaque width relative to the whole width. */
	private const MaxShare = .3;

	/** How far a plaque may sit off its row, in rows. */
	private const RowJitter = .08;

	/**
	 * Room for the legend: width and height of the free corner beside the mound, bottom right.
	 *
	 * @return array{float, float}
	 */
	public function corner(int $depth, float $width, float $height): array
	{
		$unit = $this->unit($depth + 1, $height);

		return [($width / 2 - $width * self::GroundWidth) * .9, $height * self::GroundHeight + $unit * self::RootRow * .6];
	}

	/** Height of one generation's row in a tree of that many rows (the person's included). */
	public function unit(int $rows, float $height): float
	{
		return $height * (1 - self::GroundHeight) / ($rows - 1 + self::RootRow + self::CrownRoom);
	}

	/** The y of the boundary between the person's row and the parents' row. */
	public function top(float $height, float $unit): float
	{
		return $height * (1 - self::GroundHeight) - self::RootRow * $unit;
	}

	/** Thickness of the trunk. */
	public function trunk(float $width, float $unit): float
	{
		return min($width * .05, $unit * .42);
	}

	/** @return array{float, float, bool} width and height of the plaques of generation g, and whether they are ribbons */
	public function plaqueSize(int $g, float $width, float $unit): array
	{
		$slot = $width / 2 ** $g;
		$h = $unit * self::RibbonHeight;
		if ($slot * .94 >= $h * self::RibbonMinWidth) {
			return [min($slot * .94, $h * self::RibbonWidth, $width * self::MaxShare), $h, true];
		}

		$h = $unit * self::PlaqueHeight;

		return [min($slot * .9, $h * self::PlaqueWidth, $width * self::MaxShare), $h, false];
	}

	/**
	 * The known ancestors with their plaques.
	 *
	 * @param array<int, array<int, ?Person>> $places
	 * @return array<int, array<int, PaintedNode>>
	 */
	public function nodes(array $places, float $width, float $height, PosterRandom $random): array
	{
		$unit = $this->unit(count($places), $height);
		$all = max(1, $this->count($places, 0, 0));
		$nodes = [];
		foreach ($places as $g => $row) {
			foreach ($row as $i => $person) {
				if ($person === null) {
					continue;
				}

				[$x, $y] = $this->centre($g, $i, $width, $height, $unit, $random->fork(sprintf('place%d.%d', $g, $i)));
				[$w, $h, $ribbon] = $this->plaqueSize($g, $width, $unit);
				$nodes[$g][$i] = new PaintedNode(
					$person,
					$g,
					$i,
					$x,
					$y,
					$w,
					$h,
					max($unit * .03, $this->trunk($width, $unit) * sqrt($this->count($places, $g, $i) / $all)),
					$this->count($places, $g + 1, 2 * $i) + $this->count($places, $g + 1, 2 * $i + 1) === 0,
					$ribbon,
				);
			}
		}

		return $nodes;
	}

	/** @return array{float, float} centre of the plaque: the middle of its place, moved a little at random */
	private function centre(int $g, int $i, float $width, float $height, float $unit, PosterRandom $random): array
	{
		$slot = $width / 2 ** $g;
		if ($g === 0) {
			return [$slot / 2, $this->top($height, $unit) + $unit * .5];
		}

		[$w] = $this->plaqueSize($g, $width, $unit);

		return [
			($i + .5) * $slot + $random->float(-1, 1) * min(($slot - $w) / 2 * .6, $slot * .12),
			$this->top($height, $unit) - ($g - .5) * $unit + $random->float(-1, 1) * $unit * self::RowJitter,
		];
	}

	/**
	 * Number of known people in the i-th place of generation g and above it.
	 *
	 * @param array<int, array<int, ?Person>> $places
	 */
	private function count(array $places, int $g, int $i): int
	{
		return ($places[$g][$i] ?? null) === null
			? 0
			: 1 + $this->count($places, $g + 1, 2 * $i) + $this->count($places, $g + 1, 2 * $i + 1);
	}
}
