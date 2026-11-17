<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Name plaque of the painted tree: an oval drawn by hand – a light ground, two washes of the family line's colour
 * that do not quite cover each other, a coloured rim, a thin ink line and a fine ring inside. No two are the same.
 */
final readonly class PaintedPlaque
{
	private const Ground = '#fbf6e9';

	public function __construct(private PosterBrush $brush)
	{
	}

	/** @param float $wash opacity of one wash of the colour (two are laid) */
	public function draw(PaintedNode $node, string $color, float $wash, PosterStyle $style, PosterRandom $random): string
	{
		[$x, $y, $rx, $ry] = [$node->x, $node->y, $node->w / 2, $node->h / 2];
		$ink = $style->tint(PaintedWood::Ink);
		$fill = '<path d="%s" fill="%s" fill-opacity="%.2f"/>';
		$line = '<path d="%s" fill="none" stroke="%s" stroke-opacity="%.2f" stroke-width="%.2f"/>';

		return sprintf($fill, $this->brush->blob($x, $y, $rx, $ry, $random, .02), self::Ground, 1)
			. sprintf($fill, $this->brush->blob($x, $y, $rx * .985, $ry * .97, $random, .035), $color, $wash)
			. sprintf($fill, $this->brush->blob($x + $rx * .01, $y - $ry * .01, $rx * .96, $ry * .95, $random, .05), $color, $wash)
			. sprintf($line, $this->brush->blob($x, $y, $rx, $ry, $random, .02), $color, .95, max(.2, $ry * .075))
			. sprintf($line, $this->brush->blob($x, $y, $rx * 1.03, $ry * 1.05, $random, .03), $ink, .65, max(.08, $ry * .02))
			. sprintf($line, $this->brush->blob($x, $y, $rx * .88, $ry * .82, $random, .025), $ink, .35, max(.06, $ry * .014));
	}

	/** The plaque of the person at the foot of the tree: filled with the accent colour. */
	public function root(PaintedNode $node, PosterStyle $style, PosterRandom $random): string
	{
		[$x, $y, $rx, $ry] = [$node->x, $node->y, $node->w / 2, $node->h / 2];
		$ink = $style->tint(PaintedWood::Ink);

		return sprintf('<path d="%s" fill="%s"/>', $this->brush->blob($x, $y, $rx, $ry, $random, .02), $style->accent())
			. sprintf(
				'<path d="%s" fill="none" stroke="%s" stroke-width="%.2f"/>',
				$this->brush->blob($x, $y, $rx * 1.02, $ry * 1.04, $random, .03),
				$ink,
				max(.15, $ry * .05),
			)
			. sprintf(
				'<path d="%s" fill="none" stroke="%s" stroke-opacity=".55" stroke-width="%.2f"/>',
				$this->brush->blob($x, $y, $rx * .88, $ry * .82, $random, .025),
				$style->accentInk(),
				max(.08, $ry * .018),
			);
	}

	/**
	 * Ribbon (banderole) with the name, as on old family trees: a band that sags and leans a little, with ends
	 * folded back into swallow tails. The body is light with washes of the colour, the tails and folds darker.
	 *
	 * @param ?string $ground colour of the body; null for the light ground with two washes of $color
	 */
	public function ribbon(PaintedNode $node, string $color, float $wash, ?string $ground, PosterStyle $style, PosterRandom $random): string
	{
		[$a, $b] = [$node->w / 2, $node->h / 2];
		$body = $a * .86;
		$tilt = $random->float(-1, 1) * $b * .12;
		$sag = $random->float(-1, 1) * $b * .16;
		$top = [[$node->x - $body, $node->y - $b - $tilt]];
		$bottom = [[$node->x - $body, $node->y + $b - $tilt]];
		for ($k = 1; $k <= 8; $k++) {
			$u = -1 + $k / 4;
			$off = $tilt * $u + $sag * (1 - $u * $u);
			$top[] = [$node->x + $u * $body, $node->y - $b + $off + $random->float(-1, 1) * $b * .025];
			$bottom[] = [$node->x + $u * $body, $node->y + $b + $off + $random->float(-1, 1) * $b * .025];
		}
		$outline = $this->brush->open($top) . ' ' . $this->brush->open(array_reverse($bottom), false) . 'Z';
		$ink = $style->tint(PaintedWood::Ink);
		$line = max(.08, $b * .035);

		$tails = '';
		foreach ([-1, 1] as $side) {
			$tails .= $this->tail(
				$node->x + $side * $body,
				$node->y + $tilt * $side,
				$side,
				$a - $body,
				$b,
				$ground ?? $color,
				$ink,
				$line,
				$random,
				$ground === null ? .55 : .8,
			);
		}

		$fill = '<path d="%s" fill="%s" fill-opacity="%.2f"/>';
		$washes = $ground !== null
			? sprintf($fill, $outline, $ground, 1)
			: sprintf($fill, $outline, self::Ground, 1) . sprintf($fill, $outline, $color, $wash * 1.4)
				. sprintf($fill, $this->brush->blob($node->x, $node->y + $sag * .6, $body * .93, $b * .8, $random, .06), $color, $wash);

		// A darker lower edge gives the band some body.
		$edge = array_map(static fn (array $point): array => [$point[0], $point[1] - $b * .22], $bottom);
		$shade = sprintf(
			$fill,
			$this->brush->open($edge) . ' ' . $this->brush->open(array_reverse($bottom), false) . 'Z',
			$ground === null ? $color : $ink,
			.3,
		);

		return $tails . $washes . $shade
			. sprintf(
				'<path d="%s" fill="none" stroke="%s" stroke-opacity=".8" stroke-width="%.2f" stroke-linejoin="round"/>',
				$outline,
				$ink,
				$line,
			);
	}

	/** One end of the ribbon folded back behind the body, ending in a swallow tail; $side is -1 (left) or 1. */
	private function tail(
		float $x,
		float $y,
		int $side,
		float $length,
		float $b,
		string $color,
		string $ink,
		float $line,
		PosterRandom $random,
		float $wash,
	): string
	{
		$drop = $b * $random->float(.3, .5);
		$inner = $x - $side * $length * .45;
		$outer = $x + $side * $length * $random->float(.9, 1.05);
		$tail = sprintf(
			'M%.2f,%.2f L%.2f,%.2f L%.2f,%.2f L%.2f,%.2f L%.2f,%.2fZ',
			$inner,
			$y - $b + $drop,
			$outer,
			$y - $b + $drop + $random->float(-.08, .08) * $b,
			$x + $side * $length * .55,
			$y + $drop,
			$outer,
			$y + $b + $drop + $random->float(-.08, .08) * $b,
			$inner,
			$y + $b + $drop,
		);
		$fold = sprintf('M%.2f,%.2f L%.2f,%.2f L%.2f,%.2fZ', $inner, $y + $b + $drop, $x, $y + $b, $inner, $y + $b);
		$stroke = sprintf('stroke="%s" stroke-opacity=".8" stroke-width="%.2f" stroke-linejoin="round"', $ink, $line);

		return sprintf(
			'<path d="%s" fill="%s"/><path d="%s" fill="%s" fill-opacity="%.2f" %s/>',
			$tail,
			self::Ground,
			$tail,
			$color,
			$wash,
			$stroke,
		)
			. sprintf('<path d="%s" fill="%s"/><path d="%s" fill="%s" fill-opacity=".45" %s/>', $fold, $color, $fold, $ink, $stroke);
	}
}
