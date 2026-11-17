<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\View\View;

/**
 * Ancestor tree of the poster in a rectangle measured in millimetres, its top left corner at 0,0: every generation
 * one row (the person at the bottom, ancestors above) or one column (the person on the left). Every ancestor has
 * the same share of the width (or height) as in a fan, so the tree stays symmetric. All boxes of a generation
 * write their names the same way and with the same size (PosterBoxText).
 *
 * Positions are worked out along two axes – "depth" (the generations) and "breadth" (the ancestors of one
 * generation) – and turned into x/y at the end, which is the only difference between the two directions.
 */
final readonly class PosterTree
{
	/** Share of a generation's depth taken by its boxes; the rest is for the connecting lines. */
	private const BoxDepth = .78;

	/** Share of an ancestor's breadth taken by the box. */
	private const BoxBreadth = .88;

	/** Largest breadth of a box relative to its depth: wide boxes in the upward tree, flat ones from left to right. */
	private const MaxBreadthUpward = 2.6;

	private const MaxBreadthRight = .45;

	/** Largest breadth of a box relative to the whole breadth, so that the corners beside the person stay free. */
	private const MaxShare = .4;

	public function __construct(private PosterPeople $people, private View $view, private PosterBoxText $text)
	{
	}

	/** @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first */
	public function render(array $generations, PosterStyle $style, float $width, float $height, bool $upward, float $maxFont,): string
	{
		$depthSize = ($upward ? $height : $width) / count($generations);
		$breadth = $upward ? $width : $height;
		$boxDepth = $depthSize * self::BoxDepth;
		$stroke = min(.5, $depthSize * .006);

		$connectors = '';
		$boxes = '';
		foreach ($this->people->places($generations) as $g => $row) {
			$slot = $breadth / 2 ** $g;
			$boxBreadth = $this->boxBreadth($slot, $breadth, $boxDepth, $upward);
			$people = array_values(array_filter($row));
			[$w, $h] = $upward ? [$boxBreadth, $boxDepth] : [$boxDepth, $boxBreadth];
			$text = $this->text->fit($people, $w, $h, $maxFont);
			foreach ($row as $i => $person) {
				$box = [$g * $depthSize, ($i + .5) * $slot - $boxBreadth / 2, $boxDepth, $boxBreadth];
				$boxes .= $this->box($person, $g, $i, $style, $this->rect($box, $height, $upward), $stroke, $text);
				if ($person !== null && isset($generations[$g + 1])) {
					$connectors .= $this->connector($g, $i, $depthSize, $breadth, $height, $upward);
				}
			}
		}

		return sprintf('<g fill="none" stroke="%s" stroke-width="%.2f">%s</g>', $style->muted(), $stroke, $connectors) . $boxes;
	}

	/**
	 * Room for the legend: width and height of the free corner beside the person's box (bottom right in the upward
	 * tree, bottom left from left to right).
	 *
	 * @return array{float, float}
	 */
	public function corner(int $depth, float $width, float $height, bool $upward): array
	{
		$depthSize = ($upward ? $height : $width) / ($depth + 1);
		$boxDepth = $depthSize * self::BoxDepth;
		$breadth = $upward ? $width : $height;
		$boxBreadth = $this->boxBreadth($breadth, $breadth, $boxDepth, $upward);
		// The lines to the parents bend a quarter of the breadth from each edge, so the corner ends there.
		$beside = min(($breadth - $boxBreadth) / 2, $breadth / 4) * .9;

		return $upward ? [$beside, $depthSize] : [$depthSize, $beside];
	}

	private function boxBreadth(float $slot, float $breadth, float $boxDepth, bool $upward): float
	{
		return min(
			$slot * self::BoxBreadth,
			$boxDepth * ($upward ? self::MaxBreadthUpward : self::MaxBreadthRight),
			$breadth * self::MaxShare,
		);
	}

	/** @param array{float, float, float, float} $rect x, y, width, height */
	private function box(?Person $person, int $g, int $i, PosterStyle $style, array $rect, float $stroke, BoxText $text): string
	{
		[$x, $y, $w, $h] = $rect;
		$shape = '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f" fill="%s" fill-opacity="%.2f" stroke="%s" stroke-width="%.2f"%s/>';
		$radius = min($w, $h) * .12;
		if ($person === null) {
			return sprintf($shape, $x, $y, $w, $h, $radius, $style->empty(), .6, $style->muted(), $stroke, ' stroke-dasharray="1.5 1"');
		}

		[$fill, $opacity, $class] = $this->paint($person, $g, $i, $style);

		return sprintf($shape, $x, $y, $w, $h, $radius, $this->view->e($fill), $opacity, $this->view->e($fill), $stroke, '')
			. $this->text->write($person, $class, $x + $w / 2, $y + $h / 2, $text);
	}

	/** @return array{string, float, string} fill colour, its opacity and the CSS class of the text */
	private function paint(Person $person, int $g, int $i, PosterStyle $style): array
	{
		if ($g === 0) {
			return [$style->accent(), 1.0, ' pst-root'];
		}
		if ($this->people->isUnknown($person)) {
			return [$style->empty(), 1.0, $this->people->textClass($person)];
		}

		return [$this->people->color($person, $g, $i), $this->people->isUncertain($person) ? .22 : .4, $this->people->textClass($person)];
	}

	/** Lines from the child's box to the boxes of its parents, with the bend halfway between the generations. */
	private function connector(int $g, int $i, float $depthSize, float $breadth, float $height, bool $upward): string
	{
		$from = $g * $depthSize + $depthSize * self::BoxDepth;
		$to = ($g + 1) * $depthSize;
		$bend = ($from + $to) / 2;
		$slot = $breadth / 2 ** ($g + 1);
		$child = ($i + .5) * $slot * 2;
		[$father, $mother] = [(2 * $i + .5) * $slot, (2 * $i + 1.5) * $slot];

		$points = [[$from, $child], [$bend, $child], [$bend, $father], [$to, $father], [$bend, $father], [$bend, $mother], [$to, $mother]];
		$d = '';
		foreach ($points as $n => [$depth, $across]) {
			[$x, $y] = $upward ? [$across, $height - $depth] : [$depth, $across];
			$d .= sprintf('%s%.1f,%.1f', $n === 0 || $n === 4 ? 'M' : 'L', $x, $y);
		}

		return '<path d="' . $d . '"/>';
	}

	/**
	 * @param array{float, float, float, float} $box depth from the person, breadth, size along depth, size along breadth
	 * @return array{float, float, float, float} x, y, width, height
	 */
	private function rect(array $box, float $height, bool $upward): array
	{
		[$depth, $across, $depthSize, $breadthSize] = $box;

		return $upward
			? [$across, $height - $depth - $depthSize, $breadthSize, $depthSize]
			: [$depth, $across, $depthSize, $breadthSize];
	}
}
