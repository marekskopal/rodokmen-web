<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use function array_reverse;
use function count;
use function deg2rad;
use function fmod;
use function min;
use function sprintf;

/**
 * Texts of the fan chart segments: along the arc in the inner generations, along the radius in the outer ones.
 */
final readonly class FanLabels
{
	/** Line height relative to the font size. */
	private const Leading = 1.25;

	/** Font size limits [min, max] of generations written radially. */
	private const RadialFont = [
		5 => [15, 26],
		6 => [13, 22],
		7 => [11, 18],
		8 => [9.5, 14],
		9 => [8.5, 11],
		10 => [7.5, 9],
		11 => [6.5, 8],
		12 => [6, 7],
	];

	public function __construct(private View $view, private FanGeometry $geometry)
	{
	}

	/**
	 * Lines of text centred in the segment and written along its arc; text paths are added to $defs.
	 *
	 * @param non-empty-list<array{string, float, string}> $lines [text, font size, CSS class] from the outer edge inwards
	 * @param list<string> $defs
	 */
	public function alongArc(string $id, array $lines, float $r0, float $r1, float $a0, float $a1, array &$defs): string
	{
		$middle = fmod(($a0 + $a1) / 2, 360);
		$bottom = $middle > 90 && $middle < 270;
		$radii = $this->baselines($lines, ($r0 + $r1) / 2, $bottom);
		$pad = min(3.0, ($a1 - $a0) * .04);

		$out = '';
		foreach ($lines as $j => [$text, $size, $class]) {
			$r = $radii[$j];
			$size = $this->geometry->fit($text, deg2rad($a1 - $a0 - 2 * $pad) * $r, $size);
			$defs[] = sprintf('<path id="%s_%d" d="%s"/>', $id, $j, $this->geometry->arc($r, $a0 + $pad, $a1 - $pad, $bottom));
			$out .= sprintf(
				'<text class="%s" font-size="%.1f"><textPath href="#%s_%d" startOffset="50%%" text-anchor="middle">%s</textPath></text>',
				$class,
				$size,
				$id,
				$j,
				$this->view->e($text),
			);
		}

		return $out;
	}

	/** Name, and the years when there is room, written along the radius of a narrow segment. */
	public function alongRadius(int $g, string $name, string $years, string $style, float $r0, float $r1, float $a0, float $a1): string
	{
		[$min, $max] = self::RadialFont[$g] ?? self::RadialFont[12];
		$rm = ($r0 + $r1) / 2;
		$length = $r1 - $r0 - 22;
		$arc = deg2rad($a1 - $a0) * $rm;
		$nameSize = $this->geometry->fit($name, $length, $max, $min);
		$yearsSize = $years !== '' ? $this->geometry->fit($years, $length, $max * .85, $min * .9) : 0.0;
		$two = $years !== '' && $arc >= ($nameSize + $yearsSize) * 1.15;
		if (!$two) {
			$nameSize = min($nameSize, $arc / self::Leading);
		}

		$middle = ($a0 + $a1) / 2;
		$rotate = fmod($middle, 360) < 180 ? $middle - 90 : $middle + 90;
		[$x, $y] = $this->geometry->point($rm, $middle);
		$out = sprintf('<g transform="translate(%.1f,%.1f) rotate(%.2f)">', $x, $y, $rotate);
		if (!$two) {
			return $out . $this->text('fan-t fan-t-name' . $style, $nameSize * .35, $nameSize, $name) . '</g>';
		}

		$h = ($nameSize + $yearsSize) * 1.08;

		return $out
			. $this->text('fan-t fan-t-name' . $style, -$h / 2 + $nameSize * .85, $nameSize, $name)
			. $this->text('fan-t' . $style, $h / 2 - $yearsSize * .15, $yearsSize, $years)
			. '</g>';
	}

	public function text(string $class, float $y, float $size, string $text): string
	{
		return sprintf(
			'<text class="%s" y="%.1f" text-anchor="middle" font-size="%.1f">%s</text>',
			$class,
			$y,
			$size,
			$this->view->e($text),
		);
	}

	/**
	 * Baseline radius of each line. Lines are read top to bottom: from the outer edge inwards on the upper half,
	 * from the inner edge outwards on the lower half (where the text is turned around).
	 *
	 * @param non-empty-list<array{string, float, string}> $lines
	 * @return list<float>
	 */
	private function baselines(array $lines, float $rm, bool $bottom): array
	{
		$total = 0.0;
		foreach ($lines as [, $size]) {
			$total += $size * self::Leading;
		}

		$radii = [];
		$cur = $rm + $total / 2;
		foreach ($lines as [, $size]) {
			$cur -= $size * self::Leading;
			$radii[] = $cur + $size * self::Leading * .28;
		}
		if (!$bottom) {
			return $radii;
		}

		$reversed = array_reverse($radii);
		$out = [];
		for ($j = 0; $j < count($lines); $j++) {
			$out[] = $reversed[$j] + $lines[$j][1] * .55;
		}

		return $out;
	}
}
