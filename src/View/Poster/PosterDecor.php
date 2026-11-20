<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Decoration of the historical poster, all vector (SVG filters would be turned into a picture in the PDF):
 * parchment and a cartouche behind the title (the border in the margin is PosterBorder).
 */
final readonly class PosterDecor
{
	/** The paper: a plain colour, or parchment that darkens towards the edges. */
	public function paper(PosterStyle $style, int $width, int $height): string
	{
		if (!$style->isOrnate()) {
			return sprintf('<rect width="%d" height="%d" fill="%s"/>', $width, $height, $style->paper());
		}

		return '<defs><radialGradient id="pst-paper" cx="50%" cy="45%" r="75%">'
			. '<stop offset="0" stop-color="#f9f0d9"/><stop offset=".6" stop-color="' . $style->paper() . '"/>'
			. '<stop offset="1" stop-color="#dcc28e"/></radialGradient></defs>'
			. sprintf('<rect width="%d" height="%d" fill="url(#pst-paper)"/>', $width, $height);
	}

	/** Framed panel behind the title, $pad around the text box at $x, $y of $width × $height. */
	public function cartouche(PosterStyle $style, float $x, float $y, float $width, float $height, float $pad): string
	{
		if (!$style->isOrnate()) {
			return '';
		}

		$panel = '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f" fill="%s" fill-opacity="%.2f" stroke="%s" stroke-width="%.2f"/>';

		return sprintf($panel, $x - $pad, $y - $pad, $width + 2 * $pad, $height + 2 * $pad, $pad, '#fbf4e2', .85, $style->ink(), $pad * .08)
			. sprintf(
				$panel,
				$x - $pad * .7,
				$y - $pad * .7,
				$width + 1.4 * $pad,
				$height + 1.4 * $pad,
				$pad * .7,
				'none',
				0,
				$style->muted(),
				$pad * .03,
			);
	}
}
