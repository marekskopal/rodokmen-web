<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use const M_PI;

/**
 * Flowering meadow on the mound under the painted tree, like the millefleurs grounds of old tapestries and
 * illuminations: little five-petalled flowers in white, vermilion and lapis with golden hearts, tufts of grass and
 * wild strawberries, thicker towards the ridge. The flowers keep off the trunk, the plaques and the corner of the
 * legend; the roots are drawn over them. They are as big and as many for the mound whatever the paper, so A0 is
 * not emptier than A3.
 */
final readonly class PaintedMeadow
{
	/** Size of a flower relative to the mound's height, and the room between two of them relative to that size. */
	private const Size = .085;

	private const Spacing = 1.4;

	/** Share of the mound's height that is surely inside its wavering ridge (PaintedWood::ground). */
	private const Inside = .86;

	private const MaxCount = 240;

	/** The meadow is full when this many places in a row have no room for one more flower. */
	private const Misses = 300;

	/** How far above and below its middle a flower, tuft or strawberry reaches, relative to the size of a flower. */
	private const Above = 1.3;

	private const Below = .65;

	private const Petals = [PosterPigments::White, PosterPigments::White, PosterPigments::Vermilion, PosterPigments::Lapis];

	public function __construct(private PosterBrush $brush)
	{
	}

	/**
	 * The meadow on the mound around $cx, $half to each side and $rise high, standing on $bottom; nothing within
	 * $clear of $trunk (the x of the trunk) or where $space has a plaque or the legend.
	 */
	public function draw(
		float $cx,
		float $bottom,
		float $half,
		float $rise,
		float $trunk,
		float $clear,
		PaintedSpace $space,
		PosterStyle $style,
		PosterRandom $random,
	): string
	{
		$size = $rise * self::Size;
		$spacing = $size * self::Spacing;
		// Flowers are strewn at random until no more fit.
		$placed = [];
		for ($misses = 0; $misses < self::Misses && count($placed) < self::MaxCount; $misses++) {
			$x = $cx + $random->float(-1, 1) * $half;
			$top = $bottom - $rise * self::Inside * self::hill(($x - $cx + $half) / (2 * $half));
			// Thicker towards the ridge.
			$y = $top + $size * self::Above + ($bottom - $top - $size * (self::Above + self::Below)) * $random->float(0, 1) ** 1.5;
			if (
				$y + $size * self::Below > $bottom
				|| $y - $size * self::Above < $top
				|| abs($x - $trunk) < $clear
				|| !$space->free($x, $y)
			) {
				continue;
			}
			foreach ($placed as [$px, $py]) {
				if (hypot($x - $px, $y - $py) < $spacing) {
					continue 2;
				}
			}
			$placed[] = [$x, $y];
			$misses = -1;
		}

		// From the back to the front, so that the nearer ones overlap the farther.
		usort($placed, static fn (array $a, array $b): int => $a[1] <=> $b[1]);
		$ink = $style->tint(PaintedWood::Ink);
		$out = '';
		foreach ($placed as [$x, $y]) {
			$kind = $random->float(0, 1);
			$item = match (true) {
				$kind < .55 => $this->flower($size * $random->float(.8, 1.15), $ink, $style, $random),
				$kind < .85 => $this->tuft($size * $random->float(.9, 1.3), $style, $random),
				default => $this->strawberry($size, $ink, $style, $random),
			};
			$out .= sprintf('<g transform="translate(%.2f,%.2f)">%s</g>', $x, $y, $item);
		}

		return $out;
	}

	/** Height of the mound at the share $t of its width, relative to its full height (PaintedWood::ground). */
	public static function hill(float $t): float
	{
		return $t <= 0 || $t >= 1 ? 0.0 : sin(M_PI * $t) ** 1.4;
	}

	/** Five petals round a golden heart, seen from above. */
	private function flower(float $size, string $ink, PosterStyle $style, PosterRandom $random): string
	{
		$radius = $size / 2;

		return sprintf(
			'<path d="%s" fill="%s" stroke="%s" stroke-opacity=".6" stroke-width="%.2f"/><circle r="%.2f" fill="%s"/>',
			$this->petals(5, $radius, .95, $random->float(0, 72)),
			$style->tint($random->pick(self::Petals)),
			$ink,
			max(.04, $size * .05),
			$radius * .3,
			$style->tint(PosterPigments::Gold),
		);
	}

	/** A few blades of grass. */
	private function tuft(float $size, PosterStyle $style, PosterRandom $random): string
	{
		$blades = '';
		$count = $random->int(3, 5);
		for ($k = 0; $k < $count; $k++) {
			[$x, $y] = $this->brush->reach(0, 0, -90 + ($k - ($count - 1) / 2) * 18 + $random->float(-8, 8), $size * $random->float(.7, 1));
			$blades .= sprintf('M%.2f,%.2f Q%.2f,%.2f %.2f,%.2f', ($k - ($count - 1) / 2) * $size * .08, 0, $x * .2, $y * .6, $x, $y);
		}

		return sprintf(
			'<path d="%s" fill="none" stroke="%s" stroke-opacity=".85" stroke-linecap="round" stroke-width="%.2f"/>',
			$blades,
			$style->tint(PaintedWood::GrassInk),
			max(.05, $size * .07),
		);
	}

	/** A wild strawberry: a trefoil leaf and a red berry hanging beside it. */
	private function strawberry(float $size, string $ink, PosterStyle $style, PosterRandom $random): string
	{
		$side = $random->side();
		$berry = $size * .26;
		[$bx, $by] = [$side * $size * .5, $size * .1];

		return sprintf(
			'<path d="%s" fill="%s" stroke="%s" stroke-opacity=".6" stroke-width="%.2f"/>',
			$this->petals(3, $size * .55, .7, -90 + $random->float(-15, 15), 120),
			$style->tint(PosterPigments::Green),
			$ink,
			max(.04, $size * .04),
		)
			. sprintf(
				'<path d="M0,0 Q%.2f,%.2f %.2f,%.2f" fill="none" stroke="%s" stroke-width="%.2f"/>',
				$bx * .6,
				-$size * .15,
				$bx,
				$by - $berry,
				$style->tint(PaintedWood::GrassInk),
				max(.04, $size * .04),
			)
			. sprintf(
				'<path d="M%1$.2f,%2$.2f C%3$.2f,%2$.2f %4$.2f,%5$.2f %1$.2f,%6$.2f C%7$.2f,%5$.2f %8$.2f,%2$.2f %1$.2f,%2$.2fZ" fill="%9$s" stroke="%10$s" stroke-opacity=".6" stroke-width="%11$.2f"/>'
				. '<circle cx="%12$.2f" cy="%13$.2f" r="%14$.2f" fill="%15$s"/>',
				$bx,
				$by - $berry,
				$bx + $berry * 1.4,
				$bx + $berry * .5,
				$by + $berry * 1.1,
				$by + $berry * 1.3,
				$bx - $berry * .5,
				$bx - $berry * 1.4,
				$style->tint(PosterPigments::Vermilion),
				$ink,
				max(.04, $size * .04),
				$bx - $berry * .3,
				$by,
				$berry * .18,
				$style->tint(PosterPigments::GoldLight),
			);
	}

	/**
	 * Petals or leaflets round the origin: $count of them $length long and $width (a share of the length) wide,
	 * the first turned to $angle, the others every $every degrees (all the way round by default).
	 */
	private function petals(int $count, float $length, float $width, float $angle, ?float $every = null): string
	{
		$every ??= 360 / $count;
		$d = '';
		for ($k = 0; $k < $count; $k++) {
			$turn = deg2rad($angle + $k * $every);
			$spread = atan($width);
			[$cos, $sin] = [cos($turn), sin($turn)];
			$reach = $length * 1.15;
			$d .= sprintf(
				'M0,0 C%.2f,%.2f %.2f,%.2f %.2f,%.2f C%.2f,%.2f %.2f,%.2f 0,0',
				cos($turn - $spread) * $reach,
				sin($turn - $spread) * $reach,
				$cos * $length * 1.1 - $sin * $length * $width * .35,
				$sin * $length * 1.1 + $cos * $length * $width * .35,
				$cos * $length,
				$sin * $length,
				$cos * $length * 1.1 + $sin * $length * $width * .35,
				$sin * $length * 1.1 - $cos * $length * $width * .35,
				cos($turn + $spread) * $reach,
				sin($turn + $spread) * $reach,
			);
		}

		return $d . 'Z';
	}
}
