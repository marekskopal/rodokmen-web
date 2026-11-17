<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\View\View;

/**
 * Painted family tree of the poster, like the old ones on parchment: a mound at the bottom, a trunk with the person
 * at its foot, tapering branches to the parents and on to every known ancestor, names on oval medallions and
 * leaves around them. Measured in millimetres in a rectangle with its top left corner at 0,0; the ancestors keep
 * the places of the upward tree (every one the same share of the width), unknown ones get no branch.
 *
 * All vector shapes, no filters or pictures, so the PDF stays sharp at A0. The leaves are placed by a fixed rule,
 * not at random, so that the same tree is drawn every time.
 */
final readonly class PosterPaintedTree
{
	/** Height of the mound relative to the whole height, and its half-width relative to the width. */
	private const GroundHeight = .07;

	private const GroundWidth = .22;

	/** Height of the person's row (with the trunk) relative to the other rows. */
	private const RootRow = 1.6;

	/** Medallion height relative to the row; its width relative to its height at most. */
	private const MedallionHeight = .56;

	private const MedallionWidth = 2.4;

	/** Largest medallion width relative to the whole width. */
	private const MaxShare = .3;

	/** Each generation's branches are this much thinner than the one below. */
	private const Taper = .62;

	private const Bark = '#6b4a2b';

	private const BarkLine = '#45301c';

	private const Grass = '#6f8a3c';

	/** Greens of the leaves, taken in turn. */
	private const Leaves = ['#5f7f34', '#7e9a46', '#4e6b2c', '#8aa552'];

	public function __construct(private PosterPeople $people, private View $view, private PosterBoxText $text)
	{
	}

	/** @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first */
	public function render(array $generations, PosterStyle $style, float $width, float $height, float $maxFont): string
	{
		$places = $this->people->places($generations);
		$unit = $this->unit(count($generations), $height);
		$trunk = min($width * .06, $unit * .45);

		$branches = '';
		$leaves = '';
		$medallions = '';
		foreach ($places as $g => $row) {
			[$w, $h] = $this->medallion($g, $width, $unit);
			$people = array_values(array_filter($row));
			$text = $this->text->fit($people, $w * .78, $h * .7, $maxFont);
			foreach ($row as $i => $person) {
				if ($person === null) {
					continue;
				}

				[$x, $y] = $this->centre($g, $i, $width, $height, $unit);
				$medallions .= $this->medallionShape($person, $g, $i, $style, $x, $y, $w, $h)
					. $this->text->write($person, $this->textClass($person, $g), $x, $y, $text);
				if ($g > 0) {
					// The leaves above the top row stay below the edge of the tree (and off the title).
					$size = min($unit * .28, $width / 2 ** $g * .35, ($y - $h / 2) / 1.2);
					$leaves .= $this->leavesAround($g, $i, [$x, $y, $w, $h], $width, $size, $style);
				}
				$branches .= $this->branchesUp($places, $g, $i, [$x, $y], $width, $height, $unit, $trunk);
			}
		}

		return $this->ground($width, $height, $style)
			. $this->trunk($width, $height, $unit, $trunk, $style)
			. sprintf(
				'<g fill="%s" stroke="%s" stroke-width="%.2f">%s</g>',
				$style->tint(self::Bark),
				$style->tint(self::BarkLine),
				max(.15, $trunk * .02),
				$branches,
			)
			. $leaves . $medallions;
	}

	/**
	 * Room for the legend: width and height of the free corner beside the mound, bottom right.
	 *
	 * @return array{float, float}
	 */
	public function corner(int $depth, float $width, float $height): array
	{
		$unit = $this->unit($depth + 1, $height);

		return [($width / 2 - $width * self::GroundWidth) * .9, $height * self::GroundHeight + $unit * self::RootRow * .9];
	}

	/** Height of one generation's row. */
	private function unit(int $rows, float $height): float
	{
		return $height * (1 - self::GroundHeight) / ($rows - 1 + self::RootRow);
	}

	/** @return array{float, float} centre of the i-th ancestor of generation g */
	private function centre(int $g, int $i, float $width, float $height, float $unit): array
	{
		$top = $height * (1 - self::GroundHeight) - self::RootRow * $unit;
		$y = $g === 0 ? $top + $unit * .5 : $top - ($g - .5) * $unit;

		return [($i + .5) * $width / 2 ** $g, $y];
	}

	/** @return array{float, float} width and height of the medallions of generation g */
	private function medallion(int $g, float $width, float $unit): array
	{
		$h = $unit * self::MedallionHeight;

		return [min($width / 2 ** $g * .9, $h * self::MedallionWidth, $width * self::MaxShare), $h];
	}

	private function medallionShape(Person $person, int $g, int $i, PosterStyle $style, float $x, float $y, float $w, float $h): string
	{
		$ellipse = '<ellipse cx="%.1f" cy="%.1f" rx="%.1f" ry="%.1f" fill="%s" fill-opacity="%.2f" stroke="%s" stroke-width="%.2f"/>';
		$stroke = max(.2, $h * .04);
		if ($g === 0) {
			return sprintf($ellipse, $x, $y, $w / 2, $h / 2, $style->accent(), 1, $style->tint(self::BarkLine), $stroke);
		}

		$color = $this->people->isUnknown($person) ? $style->empty() : $this->view->e($style->tint($this->people->color($person, $g, $i)));

		return sprintf($ellipse, $x, $y, $w / 2, $h / 2, '#fbf6e9', 1, $color, $stroke)
			. sprintf($ellipse, $x, $y, $w / 2, $h / 2, $color, $this->people->isUncertain($person) ? .22 : .38, $color, $stroke);
	}

	private function textClass(Person $person, int $g): string
	{
		return $g === 0 ? ' pst-root' : $this->people->textClass($person);
	}

	/**
	 * Branches from the i-th ancestor of generation g to those of its parents that are known.
	 *
	 * @param array<int, array<int, ?Person>> $places
	 * @param array{float, float} $centre of the ancestor's medallion
	 */
	private function branchesUp(
		array $places,
		int $g,
		int $i,
		array $centre,
		float $width,
		float $height,
		float $unit,
		float $trunk,
	): string
	{
		[$x, $y] = $centre;
		$out = '';
		foreach ([2 * $i, 2 * $i + 1] as $parent) {
			if (($places[$g + 1][$parent] ?? null) !== null) {
				[$px, $py] = $this->centre($g + 1, $parent, $width, $height, $unit);
				$out .= $this->branch($x, $y, $px, $py, $trunk * self::Taper ** $g * .85, $trunk * self::Taper ** ($g + 1));
			}
		}

		return $out;
	}

	/** Tapering branch from the child's medallion up to the parent's, leaving the child straight up. */
	private function branch(float $x0, float $y0, float $x1, float $y1, float $from, float $to): string
	{
		$rise = $y0 - $y1;

		return sprintf(
			'<path d="M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f L%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f Z"/>',
			$x0 - $from / 2,
			$y0,
			$x0 - $from / 2,
			$y0 - $rise * .55,
			$x1 - $to / 2,
			$y1 + $rise * .45,
			$x1 - $to / 2,
			$y1,
			$x1 + $to / 2,
			$y1,
			$x1 + $to / 2,
			$y1 + $rise * .45,
			$x0 + $from / 2,
			$y0 - $rise * .55,
			$x0 + $from / 2,
			$y0,
		);
	}

	/** Grassy mound at the bottom edge. */
	private function ground(float $width, float $height, PosterStyle $style): string
	{
		$half = $width * self::GroundWidth;
		$cx = $width / 2;

		return sprintf(
			'<path d="M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f Z" fill="%s" fill-opacity=".55" stroke="%s" stroke-width="%.2f"/>',
			$cx - $half,
			$height,
			$cx - $half * .4,
			$height - $height * self::GroundHeight * 1.5,
			$cx + $half * .4,
			$height - $height * self::GroundHeight * 1.5,
			$cx + $half,
			$height,
			$style->tint(self::Grass),
			$style->tint('#4e6b2c'),
			max(.2, $half * .004),
		);
	}

	/** Trunk from the mound up to the person, wider at its foot, with a few roots. */
	private function trunk(float $width, float $height, float $unit, float $trunk, PosterStyle $style): string
	{
		$cx = $width / 2;
		$foot = $height * (1 - self::GroundHeight * .45);
		[, $top] = $this->centre(0, 0, $width, $height, $unit);
		$rise = $foot - $top;
		$path = sprintf(
			'M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f L%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f Z',
			$cx - $trunk * .95,
			$foot,
			$cx - $trunk * .55,
			$foot - $rise * .3,
			$cx - $trunk / 2,
			$top + $rise * .4,
			$cx - $trunk / 2,
			$top,
			$cx + $trunk / 2,
			$top,
			$cx + $trunk / 2,
			$top + $rise * .4,
			$cx + $trunk * .55,
			$foot - $rise * .3,
			$cx + $trunk * .95,
			$foot,
		);

		// The roots end above the bottom edge, their line included.
		$drop = min($trunk * .25, $height * self::GroundHeight * .15);
		$roots = '';
		foreach ([-1, 1] as $side) {
			foreach ([1.0, 1.9] as $spread) {
				$roots .= sprintf(
					'M%.1f,%.1f Q%.1f,%.1f %.1f,%.1f ',
					$cx + $side * $trunk * .5,
					$foot - $trunk * .1,
					$cx + $side * $trunk * $spread,
					$foot,
					$cx + $side * $trunk * ($spread + 1),
					$foot + $drop * $spread,
				);
			}
		}

		return sprintf(
			'<path d="%s" fill="%s" stroke="%s" stroke-width="%.2f"/><path d="%s" fill="none" stroke="%s" stroke-width="%.2f" stroke-linecap="round"/>',
			$path,
			$style->tint(self::Bark),
			$style->tint(self::BarkLine),
			max(.15, $trunk * .02),
			$roots,
			$style->tint(self::Bark),
			$trunk * .18,
		);
	}

	/**
	 * Leaves above an ancestor's medallion, and beside it where there is room before the next one and the edge;
	 * turned a little differently each time.
	 *
	 * @param array{float, float, float, float} $medallion centre x, y, width, height
	 */
	private function leavesAround(int $g, int $i, array $medallion, float $width, float $size, PosterStyle $style): string
	{
		[$x, $y, $w, $h] = $medallion;
		$spots = [[-$w * .25, -$h / 2, 245.0], [$w * .25, -$h / 2, -65.0]];
		// Both neighbours put a leaf into the gap between them, each up to 1.15 × $size long.
		$gap = $width / 2 ** $g - $w;
		if ($gap >= $size * 2.3 && $x - $w / 2 - $size >= 0 && $x + $w / 2 + $size <= $width) {
			array_push($spots, [-$w / 2, 0.0, 200.0], [$w / 2, 0.0, -20.0]);
		}

		$out = '';
		foreach ($spots as $k => [$dx, $dy, $angle]) {
			$jitter = (($g * 31 + $i * 17 + $k * 7) % 11) / 10 - .5;
			$out .= $this->leaf(
				$x + $dx,
				$y + $dy,
				$size * (1 + $jitter * .3),
				$angle + $jitter * 40,
				$style->tint(self::Leaves[($i + $k) % count(self::Leaves)]),
				$style->tint(self::BarkLine),
			);
		}

		return $out;
	}

	/** One leaf with its midrib, its stalk at x, y, pointing at $angle degrees (0 = right, clockwise). */
	private function leaf(float $x, float $y, float $size, float $angle, string $color, string $rib): string
	{
		return sprintf(
			'<g transform="translate(%.1f,%.1f) rotate(%.1f)"><path d="M0,0 Q%.1f,%.1f %.1f,0 Q%.1f,%.1f 0,0 Z" fill="%s"/>'
			. '<path d="M0,0 L%.1f,0" stroke="%s" stroke-width="%.2f" stroke-opacity=".5"/></g>',
			$x,
			$y,
			$angle,
			$size * .5,
			-$size * .32,
			$size,
			$size * .5,
			$size * .32,
			$color,
			$size * .9,
			$rib,
			$size * .03,
		);
	}
}
