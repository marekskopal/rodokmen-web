<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\View\View;

/**
 * Painted family tree of the poster, like the old ones on parchment: a grassy mound, a trunk with the person at
 * its foot, branches to every known ancestor, names on oval plaques, twigs with leaves and now and then an apple.
 * Measured in millimetres in a rectangle with its top left corner at 0,0. Every generation has its row; across
 * the width the known ancestors stand as close as their trees allow (PaintedLayout), unknown ones get no branch
 * and no place of their own.
 *
 * The tree is grown, not stamped: every branch bends its own way, is as thick as the number of ancestors it
 * carries (pipe model), plaques sit a little off their row, and all outlines are drawn with an unsteady hand
 * (PosterBrush). The chance behind it is seeded by the person (PosterRandom), so the same tree is drawn every
 * time. All vector shapes, no filters or pictures, so the PDF stays sharp at A0.
 */
final readonly class PosterPaintedTree
{
	/** Share of a ribbon's width and height that the text may take. */
	private const TextWidth = .76;

	/** Share of an oval's half-axes within which the text has to stay: inside its inner ring (.82 × .88 of them). */
	private const OvalText = .78;

	private const RibbonTextHeight = .84;

	public function __construct(
		private PosterPeople $people,
		private View $view,
		private PosterBoxText $text,
		private PosterOvalText $ovalText,
		private PaintedLayout $layout,
		private PaintedLimbs $limbs,
		private PaintedWood $wood,
		private PaintedMeadow $meadow,
		private PaintedFoliage $foliage,
		private PaintedPlaque $plaque,
	) {
	}

	/** @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first */
	public function render(array $generations, PosterStyle $style, float $width, float $height, float $maxFont): string
	{
		$random = new PosterRandom('painted-tree|' . ($generations[0][0] ?? ''));
		$unit = $this->layout->unit(count($generations), $height);
		$nodes = $this->layout->nodes($this->people->places($generations), $width, $height, $random);
		[$cornerWidth, $cornerHeight] = $this->layout->corner(count($generations) - 1, $width, $height);
		$space = new PaintedSpace($nodes, $width, $height, $unit, $this->layout->top($height, $unit), $cornerWidth, $cornerHeight);

		$trunk = $this->layout->trunk($width, $unit);
		$wood = $this->trunk($nodes[0][0] ?? null, $height, $trunk, $style, $random);
		$leaves = '';
		$plaques = '';
		foreach ($nodes as $g => $row) {
			// All plaques of a generation have the same size.
			$first = array_values($row)[0] ?? null;
			if ($first === null) {
				continue;
			}
			[$w, $h, $ribbon] = [$first->w, $first->h, $first->ribbon];
			$people = array_map(static fn (PaintedNode $node): Person => $node->person, array_values($row));
			$text = $ribbon
				? $this->text->fit($people, $w * self::TextWidth, $h * self::RibbonTextHeight, $maxFont)
				: $this->ovalText->fit($people, $w, $h, $maxFont, self::OvalText);
			$leaf = min($unit * .2, $w * .5);
			foreach ($row as $node) {
				$chance = $random->fork(sprintf('%d.%d', $g, $node->i));
				[$grown, $sprays] = $this->grow($node, $nodes, $leaf, $unit * .05, $space, $style, $chance);
				array_push($wood, ...$grown);
				$leaves .= $sprays;
				$plaques .= $this->plaque($node, $style, $chance->fork('plaque'))
					. $this->ovalText->write(
						$node->person,
						$g === 0 ? ' pst-root' : $this->people->textClass($node->person),
						$node->x,
						$node->y,
						$text,
					);
			}
		}

		// Thin wood first, so that the thicker limb covers the joint.
		usort($wood, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
		// The mound lies under the trunk, as far as it fits on the sheet beside the corner of the legend.
		$half = $width * PaintedLayout::GroundWidth;
		$cx = max($half, min($width - $cornerWidth - $half, $nodes[0][0]->x ?? $width / 2));
		$rise = $height * PaintedLayout::GroundHeight * 1.1;
		$ground = $this->wood->ground($cx, $height, $half, $rise, $style, $random->fork('ground'))
			// Clear of the trunk; the roots are drawn over the flowers, as if they grew between them.
			. $this->meadow->draw(
				$cx,
				$height,
				$half,
				$rise,
				$nodes[0][0]->x ?? $cx,
				$trunk * 1.1,
				$space,
				$style,
				$random->fork('meadow'),
			);

		return $ground . implode('', array_column($wood, 1)) . $leaves . $plaques;
	}

	/**
	 * Room for the legend: width and height of the free corner beside the mound, bottom right.
	 *
	 * @return array{float, float}
	 */
	public function corner(int $depth, float $width, float $height): array
	{
		return $this->layout->corner($depth, $width, $height);
	}

	/**
	 * What grows from an ancestor: the branches to the known parents with their twigs, or the twigs of a tip.
	 *
	 * @param array<int, array<int, PaintedNode>> $nodes
	 * @return array{list<array{float, string}>, string} wood (thickness and drawing) and leaves
	 */
	private function grow(
		PaintedNode $node,
		array $nodes,
		float $leaf,
		float $grain,
		PaintedSpace $space,
		PosterStyle $style,
		PosterRandom $random,
	): array
	{
		$wood = [];
		$leaves = '';
		foreach ($this->branches($node, $nodes, $random) as $k => $limb) {
			$wood[] = [$limb->thickness(), $this->wood->limb($limb, $style, $random->fork('wood' . $k), $grain)];
			[$twigs, $sprays] = $this->twigs($limb, $leaf, $space, $style, $random->fork('twigs' . $k));
			array_push($wood, ...$twigs);
			$leaves .= $sprays;
		}
		if ($node->g > 0) {
			// A tuft of leaves peeping from behind the top edge of the plaque.
			$leaves .= $this->foliage->spray(
				$node->x + $random->float(-.3, .3) * $node->w,
				$node->y - $node->h * .3,
				-90 + $random->float(-30, 30),
				$leaf + $node->h * .3,
				$space,
				$style,
				$random->fork('tuft'),
				behind: true,
			);
		}
		if ($node->tip && $node->g > 0) {
			[$twigs, $sprays] = $this->crown($node, $leaf, $space, $style, $random->fork('crown'));
			array_push($wood, ...$twigs);
			$leaves .= $sprays;
		}

		return [$wood, $leaves];
	}

	/**
	 * Branches from an ancestor to the known parents.
	 *
	 * @param array<int, array<int, PaintedNode>> $nodes
	 * @return list<PaintedLimb>
	 */
	private function branches(PaintedNode $node, array $nodes, PosterRandom $random): array
	{
		$limbs = [];
		foreach ([2 * $node->i, 2 * $node->i + 1] as $k => $i) {
			$parent = $nodes[$node->g + 1][$i] ?? null;
			if ($parent === null) {
				continue;
			}

			$limbs[] = $this->limbs->branch($node, $parent, $random->fork('branch' . $k));
		}

		return $limbs;
	}

	/**
	 * Trunk and roots.
	 *
	 * @return list<array{float, string}> thickness and drawing
	 */
	private function trunk(?PaintedNode $root, float $height, float $trunk, PosterStyle $style, PosterRandom $random): array
	{
		if ($root === null) {
			return [];
		}

		$foot = $height * (1 - PaintedLayout::GroundHeight * .5);
		$grain = $trunk * .2;
		$wood = [];
		foreach ([-1, 1, -1, 1, $random->side()] as $k => $side) {
			$limb = $this->limbs->root(
				$root->x,
				$foot,
				$side,
				$trunk,
				$height * PaintedLayout::GroundHeight * .3,
				$random->fork('root' . $k),
			);
			$wood[] = [$limb->thickness(), $this->wood->limb($limb, $style, $random->fork('rootwood' . $k), $grain)];
		}
		$limb = $this->limbs->trunk($root->x, $foot, $root->y, $trunk, $random->fork('trunk'));
		$wood[] = [$limb->thickness() * 10, $this->wood->limb($limb, $style, $random->fork('trunkwood'), $grain)];

		return $wood;
	}

	/**
	 * Up to three twigs with leaves growing from a branch.
	 *
	 * @return array{list<array{float, string}>, string} wood (thickness and drawing) and leaves
	 */
	private function twigs(PaintedLimb $limb, float $leaf, PaintedSpace $space, PosterStyle $style, PosterRandom $random): array
	{
		$wood = [];
		$leaves = '';
		$count = $random->int(1, 3);
		for ($k = 0; $k < $count; $k++) {
			$t = $random->float(.3, .78);
			$angle = $limb->angleAt($t) + $random->side() * $random->float(30, 65);
			$twig = $this->limbs->twig(
				$limb->at($t),
				$angle,
				$leaf * $random->float(.9, 1.6),
				min($limb->widthAt($t) * .4, $leaf * .12),
				$random,
			);
			[$x, $y] = $twig->at(1);
			if (!$space->free($x, $y)) {
				continue;
			}

			$wood[] = [$twig->thickness(), $this->wood->limb($twig, $style, $random, $leaf)];
			$leaves .= $this->foliage->spray($x, $y, $twig->angleAt(1), $leaf, $space, $style, $random);
		}

		return [$wood, $leaves];
	}

	/**
	 * Twigs with leaves above the plaque of an ancestor whose parents are not known: a tip of the tree.
	 *
	 * @return array{list<array{float, string}>, string} wood (thickness and drawing) and leaves
	 */
	private function crown(PaintedNode $node, float $leaf, PaintedSpace $space, PosterStyle $style, PosterRandom $random): array
	{
		$wood = [];
		$leaves = '';
		$count = $random->int(2, 3);
		for ($k = 0; $k < $count; $k++) {
			$angle = -90 + ($k - ($count - 1) / 2) * 55 + $random->float(-12, 12);
			$start = [$node->x + cos(deg2rad($angle)) * $node->w * .3, $node->y];
			$reach = hypot(cos(deg2rad($angle)) * $node->w * .2, sin(deg2rad($angle)) * $node->h * .5);
			$twig = $this->limbs->twig(
				$start,
				$angle,
				$reach + $leaf * $random->float(.5, .9),
				min($node->girth * .6, $leaf * .12),
				$random,
			);
			[$x, $y] = $twig->at(1);
			if (!$space->free($x, $y)) {
				continue;
			}

			$wood[] = [$twig->thickness(), $this->wood->limb($twig, $style, $random, $leaf)];
			$leaves .= $this->foliage->spray($x, $y, $angle, $leaf, $space, $style, $random);
		}

		return [$wood, $leaves];
	}

	private function plaque(PaintedNode $node, PosterStyle $style, PosterRandom $random): string
	{
		if ($node->g === 0) {
			return $node->ribbon
				? $this->plaque->ribbon($node, $style->accent(), 1, $style->accent(), $style, $random)
				: $this->plaque->root($node, $style, $random);
		}

		// Men in shades of blue, women in shades of old rose: the colour tells them apart at a glance.
		$color = $this->people->isUnknown($node->person)
			? $style->empty()
			: $this->view->e($style->tint($this->people->sexShade($node->person, $node->g, $node->i)));
		$wash = $this->people->isUncertain($node->person) ? .1 : .19;

		return $node->ribbon
			? $this->plaque->ribbon($node, $color, $wash, null, $style, $random)
			: $this->plaque->draw($node, $color, $wash, $style, $random);
	}
}
