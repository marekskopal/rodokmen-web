<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use LogicException;
use Rodokmen\Site\Data\Model\Person;
use const INF;

/**
 * Where the ancestors of the painted tree sit: every generation a row above the person's, across the width as
 * PaintedSpread packs the known ancestors (no full place is kept for the unknown ones), a little off at random, so
 * that the rows do not look ruled. Also measures the wood: a branch is as thick as the number of ancestors it
 * carries (pipe model).
 */
final readonly class PaintedLayout
{
	/** Height of the mound relative to the whole height, and its half-width relative to the width. */
	public const GroundHeight = .07;

	public const GroundWidth = .22;

	/** Height of the person's row (with the trunk) and of the room for leaves above the top row, in rows. */
	private const RootRow = 1.6;

	private const CrownRoom = .3;

	/** Tall oval plaque: height relative to the row. */
	private const PlaqueHeight = .56;

	/** Ribbon: height relative to the row, width relative to its height at most and at least (else an oval). */
	private const RibbonHeight = .36;

	private const RibbonWidth = 4.6;

	private const RibbonMinWidth = 2.4;

	/**
	 * An oval at least this wide (in rows) is flat, its height relative to its width; narrower ones are tall.
	 */
	private const FlatOvalWidth = .43;

	private const FlatOvalHeight = .55;

	/** Largest plaque width relative to the whole width. */
	private const MaxShare = .3;

	/** The person of the poster stands between these shares of the width from the left. */
	private const RootLeft = .2;

	private const RootRight = .55;

	/** How far a plaque may sit off its row, in rows. */
	private const RowJitter = .08;

	public function __construct(private PaintedSpread $spread)
	{
	}

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

	/**
	 * @param float $slot room for one plaque: the distance to its nearest neighbour in the generation
	 * @return array{float, float, bool} width and height of the plaque, and whether it is a ribbon
	 */
	public function plaqueSize(float $slot, float $width, float $unit): array
	{
		$h = $unit * self::RibbonHeight;
		if ($slot * .94 >= $h * self::RibbonMinWidth) {
			return [min($slot * .94, $h * self::RibbonWidth, $width * self::MaxShare), $h, true];
		}

		$tall = $unit * self::PlaqueHeight;
		$w = min($slot * .9, $width * self::MaxShare);
		// Wide enough for the name on few lines across: a flat oval, no taller than the name needs. Narrower ones
		// stay tall, with room for the name along them or split over more lines (PosterOvalText).
		$h = $w >= $unit * self::FlatOvalWidth ? max($unit * self::RibbonHeight, $w * self::FlatOvalHeight) : $tall;

		return [$w, $h, false];
	}

	/**
	 * The known ancestors with their plaques. All plaques of a generation have the same size, set by the two
	 * that stand closest to each other.
	 *
	 * @param array<int, array<int, ?Person>> $places
	 * @return array<int, array<int, PaintedNode>>
	 */
	public function nodes(array $places, float $width, float $height, PosterRandom $random): array
	{
		$unit = $this->unit(count($places), $height);
		$all = max(1, $this->count($places, 0, 0));
		$nodes = [];
		$columns = $this->columns($places, $width);
		foreach ($columns as $g => $row) {
			$slot = $this->slot($row, $width);
			[$w, $h, $ribbon] = $this->plaqueSize($slot, $width, $unit);
			$y = $g === 0 ? $this->top($height, $unit) + $unit * .5 : $this->top($height, $unit) - ($g - .5) * $unit;
			foreach ($row as $i => $x) {
				$chance = $random->fork(sprintf('place%d.%d', $g, $i));
				// A little off its place, so that the rows do not look ruled; the person stands still.
				$aside = $g === 0 ? 0.0 : $chance->float(-1, 1) * min(($slot - $w) / 2 * .6, $w * .12, $this->leeway($columns, $g, $i));
				$nodes[$g][$i] = new PaintedNode(
					$places[$g][$i] ?? throw new LogicException('A place without an ancestor.'),
					$g,
					$i,
					$x + $aside,
					$y + ($g === 0 ? 0.0 : $chance->float(-1, 1) * $unit * self::RowJitter),
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

	/**
	 * How far an ancestor may step aside and still stay on their side of their child and of their parents (the
	 * father left, the mother right), whatever those do: under half of the smallest distance to them.
	 *
	 * @param array<int, array<int, float>> $columns
	 */
	private function leeway(array $columns, int $g, int $i): float
	{
		$x = $columns[$g][$i] ?? 0.0;
		$relatives = [$columns[$g - 1][intdiv($i, 2)] ?? null, $columns[$g + 1][2 * $i] ?? null, $columns[$g + 1][2 * $i + 1] ?? null];
		$leeway = INF;
		foreach ($relatives as $relative) {
			$leeway = $relative === null ? $leeway : min($leeway, abs($relative - $x) * .45);
		}

		return $leeway;
	}

	/**
	 * Where every known ancestor stands across the width: the tree as PaintedSpread packs it, stretched over the
	 * whole width. The person stays between RootLeft and RootRight of the width, so that the mound fits under
	 * the trunk and keeps off the legend; what room is left goes evenly to both sides.
	 *
	 * @param array<int, array<int, ?Person>> $places
	 * @return array<int, array<int, float>> generation → index → x, both in rising order
	 */
	private function columns(array $places, float $width): array
	{
		[$positions, $left, $right] = $this->spread->spread($places);
		$scale = min($width / ($right - $left), self::RootRight * $width / -$left, (1 - self::RootLeft) * $width / $right);
		$free = $width - ($right - $left) * $scale;
		$root = max(
			max(-$left * $scale, self::RootLeft * $width),
			min(-$left * $scale + $free / 2, $width - $right * $scale, self::RootRight * $width),
		);

		$columns = [];
		foreach ($positions as $g => $row) {
			foreach ($row as $i => $x) {
				$columns[$g][$i] = $root + $x * $scale;
			}
		}

		return $columns;
	}

	/**
	 * Room for one plaque of a generation: the smallest distance between two neighbours, or from the outermost
	 * ones to the edge.
	 *
	 * @param array<int, float> $row x of the ancestors of one generation, in rising order
	 */
	private function slot(array $row, float $width): float
	{
		$xs = array_values($row);
		$slot = $xs === [] ? $width : 2 * min($xs[0], $width - $xs[count($xs) - 1]);
		for ($k = 1; $k < count($xs); $k++) {
			$slot = min($slot, $xs[$k] - $xs[$k - 1]);
		}

		return $slot;
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
