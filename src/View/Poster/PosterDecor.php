<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Decoration of the historical poster, all vector (SVG filters would be turned into a picture in the PDF):
 * parchment and a scroll behind the title (the border in the margin is PosterBorder).
 */
final readonly class PosterDecor
{
	private const Sheet = '#fbf4e2';

	private const Roll = '#efe0bb';

	private const Opening = '#d9c08a';

	/** Edge of the parchment's gradient: the darkest the paper itself gets. */
	public const Edge = '#dcc28e';

	/** Corners yellowed more than the middles of the sides: a shade fading out from every corner. */
	public const Corner = '#a8844f';

	public const CornerOpacity = .3;

	/** Large, faint stains in warm browns and ochres, darkest in their middle, with a faint tide line. */
	public const Stains = ['#a0763f', '#8a6436', '#b58c4a'];

	public const StainOpacity = .09;

	/** Little rusty spots of foxing, more of them towards the edges. */
	public const Foxing = ['#9a5a2a', '#8c4f24', '#a8693a'];

	/** The strongest a spot of foxing gets: ink on it still keeps its contrast. */
	public const FoxingOpacity = .18;

	public function __construct(private PosterBrush $brush)
	{
	}

	/**
	 * The paper: a plain colour, or parchment that darkens towards the edges and more in the corners, with a few
	 * large faint stains and spots of foxing. As many of them on every format, only their size follows the paper.
	 */
	public function paper(PosterStyle $style, int $width, int $height, PosterRandom $random): string
	{
		if (!$style->isOrnate()) {
			return sprintf('<rect width="%d" height="%d" fill="%s"/>', $width, $height, $style->paper());
		}

		$short = min($width, $height);
		$out = '<defs><radialGradient id="pst-paper" cx="50%" cy="45%" r="75%">'
			. '<stop offset="0" stop-color="#f9f0d9"/><stop offset=".6" stop-color="' . $style->paper() . '"/>'
			. '<stop offset="1" stop-color="' . self::Edge . '"/></radialGradient>'
			. sprintf(
				'<radialGradient id="pst-corner"><stop offset="0" stop-color="%1$s" stop-opacity="%2$.2f"/>'
				. '<stop offset="1" stop-color="%1$s" stop-opacity="0"/></radialGradient>',
				self::Corner,
				self::CornerOpacity,
			);
		foreach (self::Stains as $k => $color) {
			$out .= sprintf(
				'<radialGradient id="pst-stain%1$d"><stop offset="0" stop-color="%2$s" stop-opacity="%3$.3f"/>'
				. '<stop offset=".8" stop-color="%2$s" stop-opacity="%4$.3f"/><stop offset="1" stop-color="%2$s" stop-opacity="0"/>'
				. '</radialGradient>',
				$k,
				$color,
				self::StainOpacity,
				self::StainOpacity * .7,
			);
		}
		$out .= '</defs>' . sprintf('<rect width="%d" height="%d" fill="url(#pst-paper)"/>', $width, $height);

		$corners = $random->fork('corners');
		foreach ([[0, 0], [$width, 0], [0, $height], [$width, $height]] as [$x, $y]) {
			$out .= sprintf('<circle cx="%d" cy="%d" r="%.1f" fill="url(#pst-corner)"/>', $x, $y, $short * $corners->float(.4, .55));
		}

		$stains = $random->fork('stains');
		for ($k = $stains->int(4, 7); $k > 0; $k--) {
			$radius = $short * $stains->float(.1, .3);
			$x = $stains->float(0, $width);
			$y = $stains->float(0, $height);
			$color = $stains->int(0, count(self::Stains) - 1);
			$outline = $this->brush->blob($x, $y, $radius, $radius * $stains->float(.7, 1), $stains, .08, 18);
			$out .= sprintf(
				'<path d="%s" fill="url(#pst-stain%d)" stroke="%s" stroke-opacity="%.3f" stroke-width="%.2f"/>',
				$outline,
				$color,
				self::Stains[$color],
				self::StainOpacity * .6,
				$short * .004,
			);
		}

		$foxing = $random->fork('foxing');
		for ($k = $foxing->int(10, 25); $k > 0; $k--) {
			// Closer to the edges: the distance from the nearest edge is mostly small.
			$in = $short * .3 * $foxing->float(0, 1) ** 2;
			[$x, $y] = match ($foxing->int(0, 3)) {
				0 => [$foxing->float(0, $width), $in],
				1 => [$foxing->float(0, $width), $height - $in],
				2 => [$in, $foxing->float(0, $height)],
				default => [$width - $in, $foxing->float(0, $height)],
			};
			$radius = $short * $foxing->float(.001, .004);
			$out .= sprintf(
				'<path d="%s" fill="%s" fill-opacity="%.2f"/>',
				$this->brush->blob($x, $y, $radius, $radius * $foxing->float(.7, 1), $foxing, .15, 8),
				$foxing->pick(self::Foxing),
				$foxing->float(.5, 1) * self::FoxingOpacity,
			);
		}

		return $out;
	}

	/**
	 * Parchment scroll behind the title, $pad around the text box at $x, $y of $width × $height: a sheet with
	 * slightly uneven edges and two ruled red lines, rolled up at both ends, lying a little above the paper.
	 */
	public function cartouche(PosterStyle $style, float $x, float $y, float $width, float $height, float $pad, PosterRandom $random): string
	{
		if (!$style->isOrnate()) {
			return '';
		}

		$roll = $pad * .9;
		// The rolls stand out above and below the sheet.
		$over = $pad * .3;
		[$left, $right, $top, $bottom] = [$x - $pad + $roll / 2, $x + $width + $pad - $roll / 2, $y - $pad, $y + $height + $pad];
		$sheet = $this->brush->open($this->edge($left, $right, $top, $pad, $random))
			. ' ' . $this->brush->open(array_reverse($this->edge($left, $right, $bottom, $pad, $random)), false) . 'Z';

		$out = sprintf(
			'<path d="%s" transform="translate(%.2f,%.2f)" fill="%s" fill-opacity=".14"/>',
			$sheet,
			$pad * .15,
			$pad * .2,
			$style->ink(),
		)
			. sprintf('<path d="%s" fill="%s" stroke="%s" stroke-width="%.2f"/>', $sheet, self::Sheet, $style->ink(), $pad * .06);
		foreach ([$top + $pad * .45, $bottom - $pad * .45] as $line) {
			$out .= sprintf(
				'<path d="M%.2f,%.2f L%.2f,%.2f" stroke="%s" stroke-opacity=".7" stroke-width="%.2f"/>',
				$left + $roll / 2,
				$line,
				$right - $roll / 2,
				$line,
				PosterPigments::Vermilion,
				$pad * .03,
			);
		}

		return $out . $this->roll($left, $top - $over, $bottom + $over, $roll, $style) . $this->roll(
			$right,
			$top - $over,
			$bottom + $over,
			$roll,
			$style,
		);
	}

	/**
	 * Top or bottom edge of the scroll's sheet at $at, not quite straight.
	 *
	 * @return non-empty-list<array{float, float}>
	 */
	private function edge(float $left, float $right, float $at, float $pad, PosterRandom $random): array
	{
		$points = [[$left, $at]];
		for ($k = 1; $k <= 8; $k++) {
			$points[] = [$left + ($right - $left) * $k / 8, $at + $random->float(-1, 1) * $pad * .06];
		}

		return $points;
	}

	/** One rolled-up end of the scroll: an upright cylinder around $x, its opening at the top. */
	private function roll(float $x, float $top, float $bottom, float $width, PosterStyle $style): string
	{
		$half = $width / 2;
		$ry = $width * .28;
		$stroke = sprintf('stroke="%s" stroke-width="%.2f"', $style->ink(), $width * .06);

		return sprintf(
			'<path d="M%.2f,%.2f L%.2f,%.2f A%.2f,%.2f 0 0 0 %.2f,%.2f L%.2f,%.2f A%.2f,%.2f 0 0 0 %.2f,%.2fZ" fill="%s" %s/>',
			$x - $half,
			$top + $ry,
			$x - $half,
			$bottom - $ry,
			$half,
			$ry,
			$x + $half,
			$bottom - $ry,
			$x + $half,
			$top + $ry,
			$half,
			$ry,
			$x - $half,
			$top + $ry,
			self::Roll,
			$stroke,
		)
			// Shade on the right of the cylinder, light on its left.
			. sprintf(
				'<path d="M%1$.2f,%2$.2f L%1$.2f,%3$.2f" stroke="%4$s" stroke-opacity=".18" stroke-width="%5$.2f"/>',
				$x + $half * .5,
				$top + $ry * 1.5,
				$bottom - $ry * .5,
				$style->ink(),
				$width * .3,
			)
			. sprintf(
				'<path d="M%1$.2f,%2$.2f L%1$.2f,%3$.2f" stroke="#fff" stroke-opacity=".4" stroke-width="%4$.2f"/>',
				$x - $half * .45,
				$top + $ry * 1.5,
				$bottom - $ry * .8,
				$width * .12,
			)
			// The opening, with the sheet curling inside.
			. sprintf('<ellipse cx="%.2f" cy="%.2f" rx="%.2f" ry="%.2f" fill="%s" %s/>', $x, $top + $ry, $half, $ry, self::Opening, $stroke)
			. sprintf(
				'<path d="M%.2f,%.2f A%.2f,%.2f 0 1 1 %.2f,%.2f" fill="none" %s/>',
				$x - $half * .5,
				$top + $ry,
				$half * .35,
				$ry * .45,
				$x + $half * .15,
				$top + $ry * 1.1,
				$stroke,
			);
	}
}
