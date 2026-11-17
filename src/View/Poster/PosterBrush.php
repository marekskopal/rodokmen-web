<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use const M_PI;

/**
 * Hand-drawn shapes as SVG path data: smooth curves through points, uneven ovals and brush strokes of changing
 * width. The unevenness is in the geometry itself (no SVG filters), so the shapes stay vector in the PDF.
 * Points are [x, y] in millimetres; angles are degrees, 0 = to the right, clockwise.
 */
final readonly class PosterBrush
{
	/**
	 * Smooth open curve through the points (Catmull–Rom spline as cubic Béziers).
	 *
	 * @param non-empty-list<array{float, float}> $points
	 * @param bool $move start with a move; otherwise with a line from the current point
	 */
	public function open(array $points, bool $move = true): string
	{
		$last = count($points) - 1;
		$d = sprintf('%s%.2f,%.2f', $move ? 'M' : 'L', $points[0][0], $points[0][1]);
		for ($i = 0; $i < $last; $i++) {
			$d .= $this->curve($points[max(0, $i - 1)], $points[$i], $points[$i + 1], $points[min($last, $i + 2)]);
		}

		return $d;
	}

	/**
	 * Smooth closed curve through the points.
	 *
	 * @param non-empty-list<array{float, float}> $points
	 */
	public function closed(array $points): string
	{
		$n = count($points);
		$d = sprintf('M%.2f,%.2f', $points[0][0], $points[0][1]);
		for ($i = 0; $i < $n; $i++) {
			$d .= $this->curve($points[($i - 1 + $n) % $n], $points[$i], $points[($i + 1) % $n], $points[($i + 2) % $n]);
		}

		return $d . 'Z';
	}

	/** Oval drawn by hand: an ellipse whose radius wavers by about $wobble (a share of the radius). */
	public function blob(float $cx, float $cy, float $rx, float $ry, PosterRandom $random, float $wobble, int $points = 14): string
	{
		$waves = [];
		foreach ([1.0, .7, .45] as $k => $share) {
			$waves[] = [$k + 2, $wobble * $share * $random->float(.5, 1), $random->float(0, 2 * M_PI)];
		}

		$outline = [];
		for ($k = 0; $k < max(6, $points); $k++) {
			$angle = 2 * M_PI * $k / max(6, $points);
			$scale = 1.0;
			foreach ($waves as [$frequency, $amplitude, $phase]) {
				$scale += $amplitude * sin($frequency * $angle + $phase);
			}
			$outline[] = [$cx + $rx * $scale * cos($angle), $cy + $ry * $scale * sin($angle)];
		}

		return $outline === [] ? '' : $this->closed($outline);
	}

	/**
	 * Points along a cubic Bézier curve, both ends included.
	 *
	 * @param array{float, float} $p0
	 * @param array{float, float} $p1
	 * @param array{float, float} $p2
	 * @param array{float, float} $p3
	 * @return non-empty-list<array{float, float}>
	 */
	public function bezier(array $p0, array $p1, array $p2, array $p3, int $steps): array
	{
		$points = [$p0];
		for ($i = 1; $i <= max(1, $steps); $i++) {
			$t = $i / max(1, $steps);
			$u = 1 - $t;
			$points[] = [
				$u ** 3 * $p0[0] + 3 * $u ** 2 * $t * $p1[0] + 3 * $u * $t ** 2 * $p2[0] + $t ** 3 * $p3[0],
				$u ** 3 * $p0[1] + 3 * $u ** 2 * $t * $p1[1] + 3 * $u * $t ** 2 * $p2[1] + $t ** 3 * $p3[1],
			];
		}

		return $points;
	}

	/**
	 * The spine moved sideways by a share of the half-width at every point (-1 = left edge, 1 = right edge).
	 *
	 * @param non-empty-list<array{float, float}> $spine
	 * @param non-empty-list<float> $widths width at every point of the spine
	 * @return non-empty-list<array{float, float}>
	 */
	public function offset(array $spine, array $widths, float $share): array
	{
		$last = count($spine) - 1;
		$moved = [];
		foreach ($spine as $i => [$x, $y]) {
			[$ax, $ay] = $spine[max(0, $i - 1)];
			[$bx, $by] = $spine[min($last, $i + 1)];
			$length = hypot($bx - $ax, $by - $ay);
			$half = ($widths[$i] ?? $widths[0]) / 2 * $share;
			$moved[] = $length > 0 ? [$x - ($by - $ay) / $length * $half, $y + ($bx - $ax) / $length * $half] : [$x, $y];
		}

		return $moved;
	}

	/**
	 * Outline of a brush stroke along the spine whose width changes from point to point.
	 *
	 * @param non-empty-list<array{float, float}> $spine
	 * @param non-empty-list<float> $widths
	 */
	public function stroke(array $spine, array $widths): string
	{
		return $this->open($this->offset($spine, $widths, -1))
			. ' ' . $this->open(array_reverse($this->offset($spine, $widths, 1)), false) . 'Z';
	}

	/** @return array{float, float} the point $distance away from $x, $y in the direction of $angle */
	public function reach(float $x, float $y, float $angle, float $distance): array
	{
		return [$x + cos(deg2rad($angle)) * $distance, $y + sin(deg2rad($angle)) * $distance];
	}

	/**
	 * @param array{float, float} $p0
	 * @param array{float, float} $p1
	 * @param array{float, float} $p2
	 * @param array{float, float} $p3
	 */
	private function curve(array $p0, array $p1, array $p2, array $p3): string
	{
		return sprintf(
			' C%.2f,%.2f %.2f,%.2f %.2f,%.2f',
			$p1[0] + ($p2[0] - $p0[0]) / 6,
			$p1[1] + ($p2[1] - $p0[1]) / 6,
			$p2[0] - ($p3[0] - $p1[0]) / 6,
			$p2[1] - ($p3[1] - $p1[1]) / 6,
			$p2[0],
			$p2[1],
		);
	}
}
