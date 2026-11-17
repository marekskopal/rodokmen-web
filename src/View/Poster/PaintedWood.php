<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use const M_PI;

/**
 * Paints the wood of the tree the way a pen-and-wash drawing does: a brown wash, a darker shade along one side,
 * a lighter streak along the other, ink outlines that do not quite follow the wash, and a few lines of bark.
 */
final readonly class PaintedWood
{
	public const Bark = '#6f4c2c';

	public const Ink = '#3d2815';

	private const Light = '#b8915f';

	private const Grass = '#6f8a3c';

	private const GrassInk = '#44602a';

	public function __construct(private PosterBrush $brush)
	{
	}

	/** @param float $grain thickness from which a limb gets lines of bark (one more for every multiple) */
	public function limb(PaintedLimb $limb, PosterStyle $style, PosterRandom $random, float $grain): string
	{
		$ink = $style->tint(self::Ink);
		$line = max(.1, min(.45, $limb->thickness() * .05));
		$out = sprintf('<path d="%s" fill="%s"/>', $this->brush->stroke($limb->spine, $limb->widths), $style->tint(self::Bark))
			. $this->streak($limb, .5, .5, $ink, .22)
			. $this->streak($limb, -.55, .3, $style->tint(self::Light), .3);

		$lines = '';
		foreach ([-1, 1] as $side) {
			$edge = $this->brush->offset($limb->spine, $limb->widths, $side * $random->float(.94, 1.04));
			$lines .= sprintf(
				'<path d="%s" stroke-width="%.2f"/>',
				$this->brush->open($this->shake($edge, $line * .5, $random)),
				$line * $random->float(.8, 1.3),
			);
		}
		for ($k = 0; $k < min(4, (int) ($limb->thickness() / max($grain, .01))); $k++) {
			$lines .= $this->barkLine($limb, $random, $line * .7);
		}

		return $out . sprintf('<g fill="none" stroke="%s" stroke-opacity=".8" stroke-linecap="round">%s</g>', $ink, $lines);
	}

	/** Grassy mound at the bottom edge: an uneven wash with an inked ridge and tufts of grass. */
	public function ground(float $cx, float $bottom, float $half, float $rise, PosterStyle $style, PosterRandom $random): string
	{
		$steps = 16;
		$ridge = [[$cx - $half, $bottom]];
		for ($k = 1; $k <= $steps; $k++) {
			$t = $k / $steps;
			$hill = sin(M_PI * $t) ** 1.4;
			$ridge[] = [$cx - $half + 2 * $half * $t, $bottom - $rise * $hill * (1 + $random->float(-.1, .1) * $hill)];
		}

		$tufts = '';
		for ($k = 2; $k < $steps - 1; $k++) {
			[$x, $y] = $ridge[$k];
			$x += $random->float(-.4, .4) * $half / $steps;
			for ($blade = 0; $blade < 3; $blade++) {
				[$tx, $ty] = $this->brush->reach($x, $y, $random->float(-125, -55), $rise * $random->float(.1, .22));
				$tufts .= sprintf('M%.2f,%.2f Q%.2f,%.2f %.2f,%.2f ', $x, $y + $rise * .03, $x, ($y + $ty) / 2, $tx, $ty);
			}
		}

		$wash = '<path d="%s" fill="%s" fill-opacity="%.2f"/>';
		// The second wash is laid a little off the first, but not below the bottom edge.
		$second = array_map(
			static fn (array $point): array => [$point[0], min($point[1], $bottom)],
			$this->shake($ridge, $rise * .06, $random),
		);

		return sprintf($wash, $this->brush->open($ridge) . 'Z', $style->tint(self::Grass), .45)
			. sprintf($wash, $this->brush->open($second) . 'Z', $style->tint(self::Grass), .25)
			. sprintf(
				'<g fill="none" stroke="%s" stroke-opacity=".75" stroke-linecap="round" stroke-width="%.2f"><path d="%s"/><path d="%s"/></g>',
				$style->tint(self::GrassInk),
				max(.12, $rise * .012),
				$this->brush->open($ridge),
				trim($tufts),
			);
	}

	/** A narrower stroke along one side of the limb: its shade or its light. */
	private function streak(PaintedLimb $limb, float $side, float $share, string $color, float $opacity): string
	{
		$widths = array_map(static fn (float $width): float => $width * $share, $limb->widths);

		return sprintf(
			'<path d="%s" fill="%s" fill-opacity="%.2f"/>',
			$this->brush->stroke($this->brush->offset($limb->spine, $limb->widths, $side), $widths),
			$color,
			$opacity,
		);
	}

	/** A line of bark running along a part of the limb. */
	private function barkLine(PaintedLimb $limb, PosterRandom $random, float $width): string
	{
		$count = count($limb->spine);
		$from = $random->int(0, max(0, $count - 4));
		$length = $random->int(3, max(3, (int) ($count * .6)));
		$spine = array_slice($limb->spine, $from, $length);
		$widths = array_slice($limb->widths, $from, $length);
		if (count($spine) < 2 || count($widths) < 2) {
			return '';
		}

		return sprintf(
			'<path d="%s" stroke-width="%.2f" stroke-opacity=".5"/>',
			$this->brush->open($this->brush->offset($spine, $widths, $random->float(-.6, .6))),
			$width,
		);
	}

	/**
	 * The points moved a little at random, as a hand does.
	 *
	 * @param non-empty-list<array{float, float}> $points
	 * @return non-empty-list<array{float, float}>
	 */
	private function shake(array $points, float $by, PosterRandom $random): array
	{
		return array_map(
			static fn (array $point): array => [$point[0] + $random->float(-$by, $by), $point[1] + $random->float(-$by, $by)],
			$points,
		);
	}
}
