<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Leaves and fruit of the painted tree: sprays of a few leaves fanning from the end of a twig, every leaf with its
 * own size, shape and green, and now and then an apple. Leaves keep off the name plaques and off the sheet's edge.
 */
final readonly class PaintedFoliage
{
	/** Greens of the leaves, with one that has started to turn. */
	private const Greens = ['#5f7f34', '#7e9a46', '#4e6b2c', '#8aa552', '#6d8f3f', '#93a04a', '#a39a45'];

	private const Fruits = ['#a8322a', '#c0552b', '#c9952c'];

	private const FruitChance = .14;

	public function __construct(private PosterBrush $brush)
	{
	}

	/**
	 * Leaves fanning from x, y around the direction $angle; $size is the length of a usual leaf.
	 *
	 * @param bool $behind the stalks start behind a plaque (only the tips have to be clear of it)
	 */
	public function spray(
		float $x,
		float $y,
		float $angle,
		float $size,
		PaintedSpace $space,
		PosterStyle $style,
		PosterRandom $random,
		bool $behind = false,
	): string
	{
		$count = $random->int(3, 5);
		$out = '';
		for ($k = 0; $k < $count; $k++) {
			$turn = $angle - 65 + 130 * $k / ($count - 1) + $random->float(-14, 14);
			$length = $size * $random->float(.7, 1.2);
			[$tipX, $tipY] = $this->brush->reach($x, $y, $turn, $length);
			if ($space->free($tipX, $tipY) && ($behind || $space->free(($x + $tipX) / 2, ($y + $tipY) / 2))) {
				$out .= $this->leaf($x, $y, $turn, $length, $style, $random);
			}
		}

		if ($out !== '' && $random->chance(self::FruitChance)) {
			[$fx, $fy] = $this->brush->reach($x, $y, $angle + $random->float(-30, 30), $size * .45);
			// The apple hangs below its stalk: its middle and its bottom have to be clear.
			$clear = $space->free($fx, $fy + $size * .34) && $space->free($fx, $fy + $size * .6);
			$out .= $clear ? $this->fruit($fx, $fy, $size * .24, $style, $random) : '';
		}

		return $out;
	}

	/** One leaf with its midrib, its stalk at x, y. Both halves of the blade are a little different. */
	private function leaf(float $x, float $y, float $angle, float $length, PosterStyle $style, PosterRandom $random): string
	{
		$upper = $length * $random->float(.2, .34);
		$lower = $length * $random->float(.2, .34);
		$belly = $random->float(.25, .45);
		$blade = sprintf(
			'M0,0 C%.2f,%.2f %.2f,%.2f %.2f,0 C%.2f,%.2f %.2f,%.2f 0,0Z',
			$length * $belly,
			-$upper * 1.35,
			$length * .75,
			-$upper,
			$length,
			$length * .75,
			$lower,
			$length * $belly,
			$lower * 1.35,
		);
		$ink = $style->tint(PaintedWood::Ink);

		return sprintf(
			'<g transform="translate(%.2f,%.2f) rotate(%.1f)"><path d="%s" fill="%s" fill-opacity=".9" stroke="%s" stroke-opacity=".5" stroke-width="%.2f"/>'
			. '<path d="M0,0 Q%.2f,%.2f %.2f,0" fill="none" stroke="%s" stroke-opacity=".45" stroke-width="%.2f"/></g>',
			$x,
			$y,
			$angle,
			$blade,
			$style->tint($random->pick(self::Greens)),
			$ink,
			max(.06, $length * .02),
			$length * .5,
			$length * $random->float(-.06, .06),
			$length * .9,
			$ink,
			max(.06, $length * .025),
		);
	}

	/** A small apple hanging on a short stalk, with a light spot. */
	private function fruit(float $x, float $y, float $radius, PosterStyle $style, PosterRandom $random): string
	{
		$ink = $style->tint(PaintedWood::Ink);

		return sprintf(
			'<path d="M%.2f,%.2f l%.2f,%.2f" stroke="%s" stroke-width="%.2f"/>',
			$x,
			$y,
			$radius * .2,
			$radius * .6,
			$ink,
			max(.08, $radius * .1),
		)
			. sprintf(
				'<path d="%s" fill="%s" stroke="%s" stroke-opacity=".6" stroke-width="%.2f"/>',
				$this->brush->blob($x + $radius * .2, $y + $radius * 1.4, $radius, $radius * .95, $random, .06, 8),
				$style->tint($random->pick(self::Fruits)),
				$ink,
				max(.06, $radius * .08),
			)
			. sprintf(
				'<circle cx="%.2f" cy="%.2f" r="%.2f" fill="#fff" fill-opacity=".35"/>',
				$x - $radius * .15,
				$y + $radius * 1.05,
				$radius * .22,
			);
	}
}
