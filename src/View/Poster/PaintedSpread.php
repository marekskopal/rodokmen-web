<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use const INF;

/**
 * How far to the side every ancestor of the painted tree stands, measured in plaque widths from the person of the
 * poster. A full place is not kept for everybody who could exist: the trees of the father's and of the mother's
 * ancestors are pushed together until they touch somewhere (Reingold–Tilford), so a line known far back may reach
 * over a shorter neighbour, and a lone known parent stands just a little to their side. The order stays: father's
 * side on the left, mother's on the right, and no tree reaches into another.
 */
final readonly class PaintedSpread
{
	/** Room between two neighbouring trees, in plaque widths. */
	private const Gap = .12;

	/** How far to their side a parent stands when the other one is not known. */
	private const Aside = .3;

	/**
	 * @param array<int, array<int, ?Person>> $places generation → index → ancestor (null: not known)
	 * @return array{array<int, array<int, float>>, float, float} positions (generation → index → x; the person at 0)
	 *     and the left and right edge of the whole tree
	 */
	public function spread(array $places): array
	{
		$offsets = [];
		$outline = $this->outline($places, 0, 0, $offsets);
		if ($outline === []) {
			return [[], -.5, .5];
		}

		ksort($offsets);
		$positions = [0 => [0 => 0.0]];
		foreach ($offsets as $g => $row) {
			ksort($row);
			foreach ($row as $i => $offset) {
				$positions[$g][$i] = ($positions[$g - 1][intdiv($i, 2)] ?? 0.0) + $offset;
			}
		}

		return [$positions, min(array_column($outline, 0)), max(array_column($outline, 1))];
	}

	/**
	 * Outline of the tree above the i-th place of generation g: its left and right edge in every generation from
	 * that place up, measured from the place itself. Notes in $offsets how far each parent stands from the child.
	 *
	 * @param array<int, array<int, ?Person>> $places
	 * @param array<int, array<int, float>> $offsets
	 * @return list<array{float, float}> empty when nobody is known there
	 */
	private function outline(array $places, int $g, int $i, array &$offsets): array
	{
		if (($places[$g][$i] ?? null) === null) {
			return [];
		}

		$sides = [$this->outline($places, $g + 1, 2 * $i, $offsets), $this->outline($places, $g + 1, 2 * $i + 1, $offsets)];
		$shifts = $this->shifts($sides[0], $sides[1]);

		$outline = [[-.5, .5]];
		foreach ($sides as $k => $side) {
			if ($side === []) {
				continue;
			}

			$offsets[$g + 1][2 * $i + $k] = $shifts[$k];
			foreach ($side as $level => [$left, $right]) {
				[$l, $r] = $outline[$level + 1] ?? [INF, -INF];
				$outline[$level + 1] = [min($l, $left + $shifts[$k]), max($r, $right + $shifts[$k])];
			}
		}

		return array_values($outline);
	}

	/**
	 * How far from the child the father's and the mother's trees stand.
	 *
	 * @param list<array{float, float}> $father
	 * @param list<array{float, float}> $mother
	 * @return array{float, float}
	 */
	private function shifts(array $father, array $mother): array
	{
		if ($father === [] || $mother === []) {
			return [$father === [] ? 0.0 : -self::Aside, $mother === [] ? 0.0 : self::Aside];
		}

		// As close as the two trees can stand without touching in any generation they share.
		$apart = 0.0;
		foreach ($father as $level => [, $right]) {
			if (isset($mother[$level])) {
				$apart = max($apart, $right - $mother[$level][0] + self::Gap);
			}
		}

		return [-$apart / 2, $apart / 2];
	}
}
