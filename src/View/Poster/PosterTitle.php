<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\View;

/**
 * Heading and subtitle of the poster in its top left corner. In the historical style they are written like the
 * opening of a medieval manuscript: on a scroll, the first letter as an illuminated initial (a gold letter on lapis
 * in a gold square, with white pen work) as tall as both lines, and the subtitle in red (a rubric).
 */
final readonly class PosterTitle
{
	/** Size of the subtitle relative to the heading's. */
	private const Sub = .45;

	/** Room between the initial and the text relative to the initial's side. */
	private const Gap = .15;

	public function __construct(private View $view, private FanGeometry $geometry, private PosterDecor $decor, private PosterBrush $brush,)
	{
	}

	/**
	 * Height of the heading and the subtitle written with the given heading size.
	 *
	 * @param bool $subtitle whether there is a subtitle below the heading
	 */
	public function height(float $size, bool $subtitle = true): float
	{
		$subSize = $subtitle ? $size * self::Sub : 0.0;

		// Below the box (box()) the subtitle's descenders.
		return self::box($size, $subSize) + $subSize * .2;
	}

	/** The heading and the subtitle with their top left corner at $left, $top, fitted to the width $available. */
	public function draw(
		PosterStyle $style,
		string $heading,
		string $subtitle,
		float $left,
		float $top,
		float $size,
		float $available,
		float $pad,
		PosterRandom $random,
	): string
	{
		[$initial, $rest, $size, $subSize, $side, $width] = $this->measure($style, $heading, $subtitle, $size, $available);
		$x = $initial === '' ? $left : $left + $side * (1 + self::Gap);

		$out = $this->decor->cartouche($style, $left, $top, $width, $side, $pad, $random->fork('scroll'));
		$first = '';
		if ($initial !== '') {
			$letter = $side * .8;
			$out .= $this->square($left, $top, $side, $style, $random->fork('initial'));
			// Centred on the square: the capitals stand about .68 of the size above the baseline.
			$first = sprintf(
				'<tspan class="pst-initial" x="%.1f" y="%.1f" font-size="%.1f" text-anchor="middle" stroke-width="%.2f">%s</tspan>',
				$left + $side / 2,
				$top + $side / 2 + $letter * .34,
				$letter,
				$letter * .015,
				$this->view->e($initial),
			);
			$rest = $rest === '' ? '' : sprintf('<tspan x="%.1f" y="%.1f">%s</tspan>', $x, $top + $size * .9, $this->view->e($rest));
		} else {
			$rest = $this->view->e($rest);
		}

		$out .= sprintf(
			'<text class="pst-t pst-title" x="%.1f" y="%.1f" font-size="%.1f">%s%s</text>',
			$x,
			$top + $size * .9,
			$size,
			$first,
			$rest,
		);
		if ($subtitle === '') {
			return $out;
		}

		return $out
			. sprintf(
				'<text class="pst-t pst-sub%s" x="%.1f" y="%.1f" font-size="%.1f">%s</text>',
				$style->isOrnate() ? ' pst-rubric' : '',
				$x,
				$top + $size * 1.2 + $subSize * 1.1,
				$subSize,
				$this->view->e($subtitle),
			);
	}

	/**
	 * Width and height of what draw() puts below and right of its top left corner: the text, and in the historical
	 * style the scroll around it.
	 *
	 * @return array{float, float}
	 */
	public function extent(PosterStyle $style, string $heading, string $subtitle, float $size, float $available, float $pad): array
	{
		[, , , $subSize, $side, $width] = $this->measure($style, $heading, $subtitle, $size, $available);
		$height = $side + $subSize * .2;
		if (!$style->isOrnate()) {
			return [$width, $height];
		}

		// The scroll reaches $pad beyond the text, its rolls a little more (PosterDecor::cartouche()).
		return [$width + $pad * 1.5, $height + $pad * 1.3];
	}

	/**
	 * The heading split into its illuminated initial and the rest, fitted to $available.
	 *
	 * @return array{string, string, float, float, float, float} initial, rest of the heading, size of the heading and
	 *     of the subtitle, side of the initial's square (the height of both lines) and the width of the text
	 */
	private function measure(PosterStyle $style, string $heading, string $subtitle, float $size, float $available): array
	{
		$initial = $style->isOrnate() ? $this->initial($heading) : '';
		$rest = mb_substr($heading, mb_strlen($initial));
		// The initial is as tall as both lines (as the heading alone without a subtitle); the text starts beside it.
		$subSize = $subtitle === '' ? 0.0 : $size * self::Sub;
		$indent = $initial === '' ? 0.0 : self::box($size, $subSize) * (1 + self::Gap);
		$subSize = $subtitle === '' ? 0.0 : $this->geometry->fit($subtitle, $available - $indent, $subSize);
		$size = $this->geometry->fit($rest, $available - $indent, $size);
		$side = self::box($size, $subSize);
		$offset = $initial === '' ? 0.0 : $side * (1 + self::Gap);
		// The glyph width is an average; wide capitals get a little room to spare.
		$textWidth = max(mb_strlen($rest) * $size, mb_strlen($subtitle) * $subSize) * FanGeometry::GlyphWidth * 1.1;

		return [$initial, $rest, $size, $subSize, $side, min($available, $offset + $textWidth)];
	}

	/** Height of the heading and the subtitle without its descenders: the side of the initial's square. */
	private static function box(float $size, float $subSize): float
	{
		return $size * 1.2 + $subSize * 1.4;
	}

	/**
	 * The letter to illuminate: the first one with its accents (also when written as combining marks), if the heading
	 * starts with a capital letter followed by more letters – the square is centred on the height of a capital.
	 */
	private function initial(string $heading): string
	{
		return preg_match('/^([\p{Lu}\p{Lt}]\p{M}*)\p{L}/u', $heading, $match) === 1 ? $match[1] : '';
	}

	/** Gold square with a lapis ground and white pen work curling from its corners. */
	private function square(float $left, float $top, float $side, PosterStyle $style, PosterRandom $random): string
	{
		$inset = $side * .08;
		$out = sprintf(
			'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" stroke="%s" stroke-width="%.2f"/>',
			$left,
			$top,
			$side,
			$side,
			PosterPigments::Gold,
			$style->ink(),
			$side * .015,
		)
			. sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" stroke="%s" stroke-width="%.2f"/>',
				$left + $inset,
				$top + $inset,
				$side - 2 * $inset,
				$side - 2 * $inset,
				PosterPigments::Lapis,
				PosterPigments::GoldShade,
				$side * .008,
			);

		// From every corner a curl towards the middle, with a dot at its start.
		$near = $inset * 1.6;
		$far = $side - $near;
		foreach ([[$near, $near, 45], [$far, $near, 135], [$far, $far, 225], [$near, $far, 315]] as $k => [$cornerX, $cornerY, $angle]) {
			$x = $left + $cornerX;
			$y = $top + $cornerY;
			$sign = $k % 2 === 0 ? 1 : -1;
			$out .= sprintf(
				'<path d="%s" fill="none" stroke="%s" stroke-opacity=".85" stroke-width="%.2f" stroke-linecap="round"/>'
				. '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s"/>',
				$this->brush->open($this->brush->spiral($x, $y, $angle - $sign * 40, $side * .2, $sign, $random->float(1, 1.4))),
				PosterPigments::White,
				$side * .012,
				$x,
				$y,
				$side * .018,
				PosterPigments::White,
			);
		}

		return $out;
	}
}
