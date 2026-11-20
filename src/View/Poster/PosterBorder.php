<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use const M_PI;

/**
 * Illuminated border of the historical poster, like the margin of a medieval manuscript: a vermilion band between
 * ink and gold rules, a gold vine winding along every side with leaves, tendrils, berries and flowers, and a gold
 * quatrefoil on lapis in every corner. The rules waver and every wave of the vine is a little different
 * (PosterBrush, PosterRandom), so it looks drawn by hand; all vector, no filters, so the PDF stays sharp at A0.
 */
final readonly class PosterBorder
{
	/** Outer and inner edge of the band as shares of the margin; the title's scroll reaches under 30 % into the margin. */
	public const Outer = .12;

	public const Inner = .66;

	/** Length of half a wave of the vine relative to the width of the band. */
	private const HalfWave = 1.25;

	/** Half the side of a corner medallion relative to the width of the band: it keeps clear of the title's scroll (its inner edge lies 69 % into the margin). */
	private const Corner = .55;

	/** How far the rules waver, and every how far along them, relative to the width of the band. */
	private const Waver = .0065;

	private const WaverStep = 1.1;

	/** Every how many crests of the vine a flower opens (and, between them, a bunch of berries). */
	private const FlowerEvery = 5;

	/** Colours of the leaves on the vermilion band. */
	private const Leaves = [PosterPigments::Verdigris, PosterPigments::Green, PosterPigments::Lapis];

	private const Flowers = [PosterPigments::White, PosterPigments::Lapis];

	public function __construct(private PosterBrush $brush)
	{
	}

	/** The border in the margin of a $width × $height sheet; nothing in the simple style. */
	public function draw(PosterStyle $style, int $width, int $height, float $margin, PosterRandom $random): string
	{
		if (!$style->isOrnate()) {
			return '';
		}

		$outer = $margin * self::Outer;
		$inner = $margin * self::Inner;
		$band = $inner - $outer;
		// The ink rules trace the edges of the band exactly.
		$outerEdge = $this->rectangle($outer, $width, $height, $band, $random->fork('outer'));
		$innerEdge = $this->rectangle($inner, $width, $height, $band, $random->fork('inner'));
		$outerGold = $this->rectangle($outer + $band * .06, $width, $height, $band, $random->fork('gold-outer'));
		$innerGold = $this->rectangle($inner - $band * .06, $width, $height, $band, $random->fork('gold-inner'));
		$out = sprintf('<path d="%s %s" fill="%s" fill-rule="evenodd"/>', $outerEdge, $innerEdge, PosterPigments::Vermilion)
			. $this->rule($outerEdge, $band * .05, $style->ink())
			. $this->rule($outerGold, $band * .025, PosterPigments::Gold)
			. $this->rule($innerGold, $band * .025, PosterPigments::Gold)
			. $this->rule($innerEdge, $band * .04, $style->ink());

		// Every side's vine runs from one corner medallion to the next, clockwise from the top left.
		$mid = ($outer + $inner) / 2;
		$corner = $band * self::Corner;
		$sides = [
			[[$mid + $corner, $mid], [1, 0], $width],
			[[$width - $mid, $mid + $corner], [0, 1], $height],
			[[$width - $mid - $corner, $height - $mid], [-1, 0], $width],
			[[$mid, $height - $mid - $corner], [0, -1], $height],
		];
		foreach ($sides as $k => [$start, $direction, $side]) {
			$out .= $this->vine($start, $direction, $side - 2 * ($mid + $corner), $band, $style, $random->fork('side' . $k));
		}
		foreach ([[$mid, $mid], [$width - $mid, $mid], [$width - $mid, $height - $mid], [$mid, $height - $mid]] as [$x, $y]) {
			$out .= $this->corner($x, $y, $corner, $style);
		}

		return $out;
	}

	/** Number of half waves of the vine on a side $length long: whole, so that it ends at the corners. */
	public function halfWaves(float $length, float $band): int
	{
		return max(2, (int) round($length / ($band * self::HalfWave)));
	}

	/**
	 * A rectangle $inset from the edges whose sides waver a little (in proportion to the $band, so that the rules
	 * never cross on small paper); the corners stay sharp.
	 */
	private function rectangle(float $inset, int $width, int $height, float $band, PosterRandom $random): string
	{
		$waver = $band * self::Waver;
		$corners = [[$inset, $inset], [$width - $inset, $inset], [$width - $inset, $height - $inset], [$inset, $height - $inset]];
		$d = '';
		foreach ($corners as $k => [$ax, $ay]) {
			[$bx, $by] = $corners[($k + 1) % 4];
			$steps = max(2, (int) round(hypot($bx - $ax, $by - $ay) / ($band * self::WaverStep)));
			$points = [[$ax, $ay]];
			for ($s = 1; $s <= $steps; $s++) {
				$t = $s / $steps;
				$wobble = $s === $steps ? 0.0 : $random->float(-$waver, $waver);
				$points[] = [
					$ax + ($bx - $ax) * $t + ($ax === $bx ? $wobble : 0.0),
					$ay + ($by - $ay) * $t + ($ay === $by ? $wobble : 0.0),
				];
			}
			$d .= $this->brush->open($points, $k === 0);
		}

		return $d . 'Z';
	}

	/** A rule along the outline $d (rectangle()). */
	private function rule(string $d, float $stroke, string $color): string
	{
		return sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="%.2f"/>', $d, $color, $stroke);
	}

	/**
	 * Gold vine along one side, waving across the middle of the band: a leaf leaning out of every crest, now and
	 * then a flower or berries instead, and a curling tendril where the stem crosses the middle.
	 *
	 * @param array{float, float} $start
	 * @param array{int, int} $direction
	 */
	private function vine(array $start, array $direction, float $length, float $band, PosterStyle $style, PosterRandom $random): string
	{
		$halves = $this->halfWaves($length, $band);
		$half = $length / $halves;
		// Across the side, to the right of the direction.
		[$nx, $ny] = [-$direction[1], $direction[0]];
		$at = static fn (float $along, float $across): array => [
			$start[0] + $direction[0] * $along + $nx * $across,
			$start[1] + $direction[1] * $along + $ny * $across,
		];
		$angle = rad2deg(atan2($direction[1], $direction[0]));

		$heights = [];
		for ($h = 0; $h < $halves; $h++) {
			$heights[] = $band * $random->float(.15, .19);
		}

		$spine = [];
		$widths = [];
		$steps = 6;
		foreach ($heights as $h => $amplitude) {
			$sign = $h % 2 === 0 ? 1 : -1;
			for ($s = 0; $s < $steps; $s++) {
				$t = $s / $steps;
				$spine[] = $at(($h + $t) * $half, $sign * $amplitude * sin(M_PI * $t));
				// The stem thins out at the crests.
				$widths[] = $band * .06 * (1 - .35 * sin(M_PI * $t));
			}
		}
		$spine[] = $at($length, 0);
		$widths[] = $band * .06;

		$out = sprintf(
			'<path d="%s" fill="%s" stroke="%s" stroke-width="%.2f"/>',
			$this->brush->stroke($spine, $widths),
			PosterPigments::Gold,
			PosterPigments::GoldShade,
			$band * .006,
		);
		foreach ($heights as $h => $amplitude) {
			$sign = $h % 2 === 0 ? 1 : -1;
			$chance = $random->fork('wave' . $h);
			[$x, $y] = $at(($h + .5) * $half, $sign * $amplitude);
			$out .= match ($h % self::FlowerEvery) {
				2 => $this->flower($x, $y, $band * .17, $style, $chance),
				4 => $this->berries($x, $y, $angle + $sign * 90, $band * .2),
				default => $this->leaf($x, $y, $angle + $sign * $chance->float(40, 60), $band * $chance->float(.24, .3), $h, $chance),
			};
			if ($h === 0) {
				continue;
			}

			[$x, $y] = $at($h * $half, 0);
			$out .= $this->tendril($x, $y, $angle + $sign * 120, $band * .22, $sign, $chance);
		}

		return $out;
	}

	/** A heart-shaped leaf on a gold stalk at x, y, with a gold rim and a light midrib; $n picks its colour. */
	private function leaf(float $x, float $y, float $angle, float $length, int $n, PosterRandom $random): string
	{
		$stalk = $length * .3;
		$blade = sprintf(
			'M%.2f,0 C%.2f,%.2f %.2f,%.2f %.2f,%.2f C%.2f,%.2f %.2f,0 %.2f,0'
			. ' C%.2f,0 %.2f,%.2f %.2f,%.2f C%.2f,%.2f %.2f,%.2f %.2f,0Z',
			$stalk,
			$stalk - $length * .25,
			-$length * .42,
			$stalk + $length * .3,
			-$length * .55,
			$stalk + $length * .5,
			-$length * .2,
			$stalk + $length * .6,
			-$length * .08,
			$stalk + $length * .7,
			$stalk + $length * .78,
			$stalk + $length * .7,
			$stalk + $length * .6,
			$length * .08,
			$stalk + $length * .5,
			$length * .2,
			$stalk + $length * .3,
			$length * .55,
			$stalk - $length * .25,
			$length * .42,
			$stalk,
		);

		return sprintf(
			'<g transform="translate(%.2f,%.2f) rotate(%.1f)">'
			. '<path d="M0,0 Q%.2f,%.2f %.2f,0" fill="none" stroke="%s" stroke-width="%.2f"/>'
			. '<path d="%s" fill="%s" stroke="%s" stroke-width="%.2f"/>'
			. '<path d="M%.2f,0 Q%.2f,%.2f %.2f,0" fill="none" stroke="%s" stroke-opacity=".6" stroke-width="%.2f"/></g>',
			$x,
			$y,
			$angle,
			$stalk / 2,
			$random->float(-.1, .1) * $length,
			$stalk,
			PosterPigments::Gold,
			$length * .04,
			$blade,
			self::Leaves[$n % count(self::Leaves)],
			PosterPigments::Gold,
			$length * .035,
			$stalk + $length * .05,
			$stalk + $length * .4,
			$random->float(-.04, .04) * $length,
			$stalk + $length * .65,
			PosterPigments::GoldLight,
			$length * .025,
		);
	}

	/** A hairline tendril curling into a spiral from x, y, $sign = 1 clockwise, ending in a gold ball. */
	private function tendril(float $x, float $y, float $angle, float $size, int $sign, PosterRandom $random): string
	{
		$points = $this->brush->spiral($x, $y, $angle, $size, $sign, $random->float(1.1, 1.5));
		[$bx, $by] = $points[5];

		return sprintf(
			'<path d="%s" fill="none" stroke="%s" stroke-width="%.2f" stroke-linecap="round"/>',
			$this->brush->open($points),
			PosterPigments::Gold,
			$size * .07,
		) . $this->ball($bx, $by, $size * .14);
	}

	/** Three gold berries on stalks fanning from x, y towards $angle. */
	private function berries(float $x, float $y, float $angle, float $size): string
	{
		$out = '';
		foreach ([-35 => .7, 0 => .9, 35 => .7] as $turn => $reach) {
			[$bx, $by] = $this->brush->reach($x, $y, $angle + $turn, $size * $reach);
			$out .= sprintf(
				'<path d="M%.2f,%.2f L%.2f,%.2f" stroke="%s" stroke-width="%.2f"/>',
				$x,
				$y,
				$bx,
				$by,
				PosterPigments::Gold,
				$size * .06,
			) . $this->ball($bx, $by, $size * .14);
		}

		return $out;
	}

	/** Five round petals around a gold heart. */
	private function flower(float $x, float $y, float $radius, PosterStyle $style, PosterRandom $random): string
	{
		$color = $random->pick(self::Flowers);
		$turn = $random->float(0, 72);
		$out = '';
		for ($p = 0; $p < 5; $p++) {
			[$px, $py] = $this->brush->reach($x, $y, $turn + $p * 72, $radius * .55);
			$out .= sprintf(
				'<path d="%s" fill="%s" stroke="%s" stroke-opacity=".7" stroke-width="%.2f"/>',
				$this->brush->blob($px, $py, $radius * .5, $radius * .5, $random, .08, 8),
				$color,
				$style->ink(),
				$radius * .05,
			);
		}

		return $out . $this->ball($x, $y, $radius * .38);
	}

	/** A ball of burnished gold. */
	private function ball(float $x, float $y, float $radius): string
	{
		return sprintf(
			'<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s" stroke="%s" stroke-width="%.2f"/>'
			. '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s" fill-opacity=".8"/>',
			$x,
			$y,
			$radius,
			PosterPigments::Gold,
			PosterPigments::GoldShade,
			$radius * .15,
			$x - $radius * .3,
			$y - $radius * .3,
			$radius * .35,
			PosterPigments::GoldLight,
		);
	}

	/** Corner medallion: a lapis square with a gold rule, a gold quatrefoil and a vermilion one inside it. */
	private function corner(float $x, float $y, float $half, PosterStyle $style): string
	{
		$lobe = $half * .38;
		$out = sprintf(
			'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" stroke="%s" stroke-width="%.2f"/>',
			$x - $half,
			$y - $half,
			2 * $half,
			2 * $half,
			PosterPigments::Lapis,
			$style->ink(),
			$half * .05,
		)
			. sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="none" stroke="%s" stroke-width="%.2f"/>',
				$x - $half * .86,
				$y - $half * .86,
				1.72 * $half,
				1.72 * $half,
				PosterPigments::Gold,
				$half * .05,
			)
			. sprintf(
				'<path d="%s" fill="%s" stroke="%s" stroke-width="%.2f"/>',
				$this->quatrefoil($x, $y, $lobe, $half * .4),
				PosterPigments::Gold,
				PosterPigments::GoldShade,
				$half * .04,
			)
			. sprintf(
				'<path d="%s" fill="%s" stroke="%s" stroke-width="%.2f"/>',
				$this->quatrefoil($x, $y, $lobe * .62, $half * .22),
				PosterPigments::Vermilion,
				$style->ink(),
				$half * .025,
			)
			. $this->ball($x, $y, $half * .12);

		// Burnished spots on the upper left lobes.
		foreach ([[-1, 0], [0, -1]] as [$dx, $dy]) {
			$out .= sprintf(
				'<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s" fill-opacity=".55"/>',
				$x + $dx * $lobe * 1.3 - $half * .05,
				$y + $dy * $lobe * 1.3 - $half * .05,
				$half * .07,
				PosterPigments::GoldLight,
			);
		}

		return $out;
	}

	/**
	 * Outline of four round lobes of radius $radius whose centres are $distance from x, y (up, right, down, left).
	 */
	private function quatrefoil(float $x, float $y, float $distance, float $radius): string
	{
		// Where two neighbouring lobes cross, on the diagonal: the cusp of the outline.
		$cusp = ($distance + sqrt(max(0.0, 2 * $radius * $radius - $distance * $distance))) / 2;
		// The outer arc of a lobe is the larger one only when the lobe's centre lies inside the line between its cusps.
		$large = $distance > $cusp ? 1 : 0;
		$d = sprintf('M%.2f,%.2f', $x - $cusp, $y - $cusp);
		foreach ([[1, -1], [1, 1], [-1, 1], [-1, -1]] as [$sx, $sy]) {
			$d .= sprintf(' A%.2f,%.2f 0 %d 1 %.2f,%.2f', $radius, $radius, $large, $x + $sx * $cusp, $y + $sy * $cusp);
		}

		return $d . 'Z';
	}
}
