<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use const M_PI;

/**
 * Shapes of the wood of the painted tree. Every limb is a curve of its own: where it bends, how much it sways and
 * how its width wavers come from the repeatable chance, so no two branches are the same.
 */
final readonly class PaintedLimbs
{
	public function __construct(private PosterBrush $brush)
	{
	}

	/**
	 * Branch from a child's plaque up to a parent's: it leaves sideways and arrives from below, as if the parent's
	 * plaque hung on its end. Widest at the fork.
	 */
	public function branch(PaintedNode $child, PaintedNode $parent, float $from, float $to, PosterRandom $random): PaintedLimb
	{
		$rise = $child->y - $parent->y;
		$start = [$child->x + ($parent->x <=> $child->x) * $child->w * .12, $child->y];
		$span = $parent->x - $start[0];
		$sway = $random->float(-1, 1) * $parent->w * .1;

		return $this->limb(
			$start,
			[$start[0] + $span * $random->float(.05, .35), $start[1] - $rise * $random->float(.2, .5)],
			[$parent->x - $span * $random->float(-.08, .18) + $sway, $parent->y + $rise * $random->float(.3, .6)],
			[$parent->x, $parent->y],
			[$from, $to, .3],
			min($rise * .04, abs($span) * .08 + $to),
			$random,
			12,
		);
	}

	/** Trunk from the ground up to the person, flaring at its foot and leaning a little. */
	public function trunk(float $x, float $foot, float $top, float $width, PosterRandom $random): PaintedLimb
	{
		$rise = $foot - $top;
		$start = [$x + $random->float(-.25, .25) * $width, $foot];

		return $this->limb(
			$start,
			[$start[0] + $random->float(-.3, .3) * $width, $foot - $rise * .35],
			[$x + $random->float(-.35, .35) * $width, $top + $rise * .35],
			[$x, $top],
			[$width * 1.05, $width, .9],
			$width * .12,
			$random,
			10,
		);
	}

	/** Root creeping from the foot of the trunk along the ground to one side (-1 left, 1 right). */
	public function root(float $x, float $foot, int $side, float $trunk, float $drop, PosterRandom $random): PaintedLimb
	{
		$start = [$x + $side * $trunk * $random->float(.15, .5), $foot - $trunk * .35];
		$end = [$x + $side * $trunk * $random->float(1.5, 3.2), $foot + $drop * $random->float(.1, 1)];

		return $this->limb(
			$start,
			[$start[0] + $side * $trunk * .3, $foot + $drop * .2],
			[$end[0] - $side * $trunk * $random->float(.4, .9), $end[1] - $drop * $random->float(.2, .8)],
			$end,
			[$trunk * $random->float(.3, .42), $trunk * .05, .4],
			$trunk * .06,
			$random,
			7,
		);
	}

	/**
	 * Thin twig growing from a point in the given direction, bending on its way.
	 *
	 * @param array{float, float} $start
	 */
	public function twig(array $start, float $angle, float $length, float $width, PosterRandom $random): PaintedLimb
	{
		$bend = $random->float(-28, 28);

		return $this->limb(
			$start,
			$this->brush->reach($start[0], $start[1], $angle - $bend, $length * .4),
			$this->brush->reach($start[0], $start[1], $angle - $bend / 2, $length * .75),
			$this->brush->reach($start[0], $start[1], $angle, $length),
			[$width, $width * .25, 0.0],
			$length * .03,
			$random,
			6,
		);
	}

	/**
	 * A limb along a Bézier curve that sways sideways (not at its ends) and whose width tapers and wavers.
	 *
	 * @param array{float, float} $p0
	 * @param array{float, float} $p1
	 * @param array{float, float} $p2
	 * @param array{float, float} $p3
	 * @param array{float, float, float} $taper width at the start, at the end, and the extra share where it flares at the start
	 */
	private function limb(
		array $p0,
		array $p1,
		array $p2,
		array $p3,
		array $taper,
		float $sway,
		PosterRandom $random,
		int $steps,
	): PaintedLimb
	{
		[$from, $to, $flare] = $taper;
		$curve = $this->brush->bezier($p0, $p1, $p2, $p3, $steps);
		$last = count($curve) - 1;
		$phase = $random->float(0, 2 * M_PI);
		$waves = $random->float(.8, 2.2);

		$push = [];
		$widths = [];
		foreach (array_keys($curve) as $i) {
			$t = $i / $last;
			$push[] = $sway * sin(M_PI * $t) * sin($waves * 2 * M_PI * $t + $phase) * 2;
			$widths[] = max(.05, ($from + ($to - $from) * $t) * (1 + $flare * (1 - $t) ** 3) * (1 + $random->float(-.07, .07)));
		}

		// offset() moves by a share of half of the given "widths": here the sideways push of every point.
		return new PaintedLimb($this->brush->offset($curve, $push, 1), $widths);
	}
}
