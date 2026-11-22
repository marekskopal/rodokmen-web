<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;

/**
 * Where the tree of the poster starts below the title in the top left (or top right) corner: as high as it can without its boxes
 * running into the title, at most below the whole band of the title. A tree that is shallow on the left (or whose
 * oldest generation leaves the corner empty) so takes the whole height of the sheet. Its branches and leaves may
 * grow behind the title, the names may not.
 */
final readonly class PosterTreeBand
{
	/** The tree tries this many heights between the whole area and the area below the title's band. */
	private const Steps = 8;

	/** Room kept between the boxes and the title, relative to the heading's size. */
	private const Clearance = .4;

	public function __construct(private PosterTree $tree, private PosterPaintedTree $paintedTree, private PosterTitle $title)
	{
	}

	/**
	 * @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first
	 * @param array{float, float} $area width and height of the area inside the margins
	 * @param array{float, float} $title width and height of the title in the area's top corner (PosterTitle::extent())
	 * @param float $size the heading's size
	 * @param bool $subtitle whether the title has a subtitle
	 * @param bool $right whether the title stands in the top right corner (otherwise the top left one)
	 * @param list<Person> $descendants the person's descendants on the trunk of the painted tree
	 * @return float how far below the top of the area the tree starts
	 */
	public function of(
		PosterLayout $layout,
		array $generations,
		array $area,
		array $title,
		float $size,
		bool $subtitle,
		bool $right = false,
		array $descendants = [],
	): float
	{
		[$width, $height] = $area;
		$band = $this->title->height($size, $subtitle) + $size * .8;
		for ($step = 0; $step < self::Steps; $step++) {
			$shift = $band * $step / self::Steps;
			$boxes = $this->boxes($layout, $generations, $width, $height - $shift, $descendants);
			if ($right) {
				// Mirrored, the title stands in the top left corner again.
				$boxes = array_map(static fn (array $box): array => [$width - $box[0] - $box[2], $box[1], $box[2], $box[3]], $boxes);
			}
			if ($this->clear($boxes, $shift, $title, $size * self::Clearance)) {
				return $shift;
			}
		}

		return $band;
	}

	/**
	 * @param non-empty-list<list<?string>> $generations
	 * @param list<Person> $descendants
	 * @return list<array{float, float, float, float}> x, y, width and height of every box with a name
	 */
	private function boxes(PosterLayout $layout, array $generations, float $width, float $height, array $descendants): array
	{
		return match ($layout) {
			PosterLayout::PaintedTree => $this->paintedTree->plaques($generations, $width, $height, $descendants),
			PosterLayout::Tree, PosterLayout::TreeRight => $this->tree->boxes(
				$generations,
				$width,
				$height,
				$layout === PosterLayout::Tree,
			),
			PosterLayout::Fan, PosterLayout::HalfFan => [],
		};
	}

	/**
	 * Whether none of the boxes of a tree moved $shift down reaches the title.
	 *
	 * @param list<array{float, float, float, float}> $boxes
	 * @param array{float, float} $title
	 */
	private function clear(array $boxes, float $shift, array $title, float $clearance): bool
	{
		// The title starts in the corner: a box clears it when it lies right of it or below it.
		foreach ($boxes as [$x, $y]) {
			if ($x < $title[0] + $clearance && $y + $shift < $title[1] + $clearance) {
				return false;
			}
		}

		return true;
	}
}
