<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

/**
 * Geometry of the fan chart. The centre is at 0,0; angles are degrees clockwise from the top.
 */
final readonly class FanGeometry
{
	/** Average glyph width relative to the font size. */
	public const GlyphWidth = .56;

	/** @return array{float, float} */
	public function point(float $r, float $angle): array
	{
		$t = deg2rad($angle);

		return [$r * sin($t), -$r * cos($t)];
	}

	/** SVG path of a ring segment between two radii and two angles. */
	public function sector(float $r0, float $r1, float $a0, float $a1): string
	{
		$large = $a1 - $a0 > 180 ? 1 : 0;
		[$x0, $y0] = $this->point($r1, $a0);
		[$x1, $y1] = $this->point($r1, $a1);
		[$x2, $y2] = $this->point($r0, $a1);
		[$x3, $y3] = $this->point($r0, $a0);

		return sprintf('M%.1f,%.1f A%.1f,%.1f 0 %d 1 %.1f,%.1f', $x0, $y0, $r1, $r1, $large, $x1, $y1)
			. sprintf(' L%.1f,%.1f A%.1f,%.1f 0 %d 0 %.1f,%.1f Z', $x2, $y2, $r0, $r0, $large, $x3, $y3);
	}

	/** Arc used as a text path; on the lower half it runs backwards so that the text is not upside down. */
	public function arc(float $r, float $a0, float $a1, bool $backwards): string
	{
		[$xa, $ya] = $this->point($r, $backwards ? $a1 : $a0);
		[$xb, $yb] = $this->point($r, $backwards ? $a0 : $a1);
		$large = $a1 - $a0 > 180 ? 1 : 0;

		return sprintf('M%.1f,%.1f A%.1f,%.1f 0 %d %d %.1f,%.1f', $xa, $ya, $r, $r, $large, $backwards ? 0 : 1, $xb, $yb);
	}

	/** Font size that fits the text into the available length, within the limits. */
	public function fit(string $text, float $available, float $max, float $min = 0.0): float
	{
		return max($min, min($max, $available / (self::GlyphWidth * max(mb_strlen($text), 1))));
	}
}
