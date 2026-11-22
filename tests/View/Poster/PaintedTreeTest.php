<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\DemoFamily;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\Poster\PaintedLayout;
use Rodokmen\Site\View\Poster\PaintedLimb;
use Rodokmen\Site\View\Poster\PaintedLimbs;
use Rodokmen\Site\View\Poster\PaintedMeadow;
use Rodokmen\Site\View\Poster\PaintedNode;
use Rodokmen\Site\View\Poster\PaintedSpace;
use Rodokmen\Site\View\Poster\PaintedSpread;
use Rodokmen\Site\View\Poster\PaintedWood;
use Rodokmen\Site\View\Poster\PosterBrush;
use Rodokmen\Site\View\Poster\PosterPeople;
use Rodokmen\Site\View\Poster\PosterRandom;
use Rodokmen\Site\View\Poster\PosterStyle;
use const PREG_SET_ORDER;

/** Building blocks of the painted tree: repeatable chance, hand-drawn shapes, places of the ancestors. */
final class PaintedTreeTest extends TestCase
{
	public function testChanceIsRepeatableAndForksAreIndependent(): void
	{
		$a = new PosterRandom('seed');
		$b = new PosterRandom('seed');
		$first = [$a->float(0, 1), $a->int(1, 100), $a->float(-5, 5)];

		self::assertSame($first, [$b->float(0, 1), $b->int(1, 100), $b->float(-5, 5)]);
		self::assertNotSame($first[0], new PosterRandom('other')->float(0, 1));
		// A fork does not depend on how much of the parent's sequence was used.
		self::assertSame($a->fork('leaf')->float(0, 1), new PosterRandom('seed')->fork('leaf')->float(0, 1));
		self::assertNotSame($a->fork('leaf')->float(0, 1), $a->fork('twig')->float(0, 1));

		self::assertSame(3.0, $a->float(3, 3));
		self::assertSame(7, $a->int(7, 2), 'an empty range gives its start');
		self::assertContains($a->side(), [-1, 1]);
		self::assertContains($a->pick(['x', 'y']), ['x', 'y']);
		for ($k = 0; $k < 50; $k++) {
			$value = $a->float(-2, 4);
			self::assertGreaterThanOrEqual(-2, $value);
			self::assertLessThanOrEqual(4, $value);
		}
	}

	public function testBrushDrawsSmoothShapes(): void
	{
		$brush = new PosterBrush();

		self::assertSame('M0.00,0.00 C1.67,0.00 8.33,0.00 10.00,0.00', $brush->open([[0.0, 0.0], [10.0, 0.0]]));
		self::assertStringStartsWith('L0.00,0.00 C', $brush->open([[0.0, 0.0], [10.0, 0.0]], false));
		self::assertStringEndsWith('Z', $brush->closed([[0.0, 0.0], [10.0, 0.0], [5.0, 8.0]]));
		self::assertSame([[0.0, 0.0], [5.0, 0.0], [10.0, 0.0]], $brush->bezier([0.0, 0.0], [10 / 3, 0.0], [20 / 3, 0.0], [10.0, 0.0], 2));

		// A stroke 4 wide along a horizontal line: its edges run 2 above and 2 below.
		self::assertSame([[0.0, 2.0], [10.0, 2.0]], $brush->offset([[0.0, 0.0], [10.0, 0.0]], [4.0, 4.0], 1));
		self::assertSame([[0.0, -2.0], [10.0, -2.0]], $brush->offset([[0.0, 0.0], [10.0, 0.0]], [4.0, 4.0], -1));
		$stroke = $brush->stroke([[0.0, 0.0], [10.0, 0.0]], [4.0, 4.0]);
		self::assertStringStartsWith('M0.00,-2.00', $stroke);
		self::assertStringContainsString(' L10.00,2.00', $stroke);
		self::assertStringEndsWith('0.00,2.00Z', $stroke);

		[$x, $y] = $brush->reach(1, 1, 90, 5);
		self::assertEqualsWithDelta(1.0, $x, 1e-9);
		self::assertEqualsWithDelta(6.0, $y, 1e-9);
	}

	public function testBlobWaversAroundAnEllipse(): void
	{
		$blob = new PosterBrush()->blob(50, 20, 10, 5, new PosterRandom('blob'), .05);

		self::assertStringEndsWith('Z', $blob);
		self::assertNotSame($blob, new PosterBrush()->blob(50, 20, 10, 5, new PosterRandom('another'), .05), 'no two are the same');
		preg_match_all('/ (-?[\d.]+),(-?[\d.]+)(?= C|Z)/', $blob, $points, PREG_SET_ORDER);
		self::assertCount(14, $points);
		foreach ($points as [, $x, $y]) {
			$radius = hypot(((float) $x - 50) / 10, ((float) $y - 20) / 5);
			self::assertEqualsWithDelta(1.0, $radius, .12);
		}
	}

	public function testLayoutPlacesEveryKnownAncestor(): void
	{
		$layout = new PaintedLayout(new PaintedSpread());
		$places = $this->demoPlaces();
		$nodes = $layout->nodes($places, 800, 500, new PosterRandom('layout'));
		self::assertEquals($nodes, $layout->nodes($places, 800, 500, new PosterRandom('layout')));

		$known = 0;
		foreach ($places as $g => $row) {
			foreach ($row as $i => $person) {
				$known += $person !== null ? 1 : 0;
				self::assertSame($person !== null, isset($nodes[$g][$i]), 'a plaque for every known ancestor and no other');
			}
		}
		self::assertGreaterThan(30, $known);

		$root = $nodes[0][0];
		self::assertGreaterThanOrEqual(.2 * 800, $root->x, 'the person stands where the mound fits under the trunk');
		self::assertLessThanOrEqual(.55 * 800 + 1e-9, $root->x);
		self::assertTrue($root->ribbon);
		self::assertFalse($root->tip);
		self::assertEqualsWithDelta($layout->trunk(800, $layout->unit(7, 500)), $root->girth, 1e-9);
	}

	public function testDescendantsStandOnALongerTrunkAboveTheRoots(): void
	{
		$layout = new PaintedLayout(new PaintedSpread());
		$places = $this->demoPlaces();
		$people = array_slice(array_values(array_filter([...$places[1], ...$places[2]])), 0, 4);
		self::assertCount(4, $people);
		$plain = $layout->nodes($places, 800, 500, new PosterRandom('layout'))[0][0];
		$nodes = $layout->nodes($places, 800, 500, new PosterRandom('layout'), 4);
		$root = $nodes[0][0];
		$unit = $layout->unit(count($places), 500, 4);
		$below = $layout->descendants($nodes, $people, $unit);

		self::assertLessThan($layout->unit(count($places), 500), $unit, 'the generations make room for the trunk');
		self::assertLessThan($plain->y, $root->y, 'the person stands higher');
		self::assertCount(4, $below);
		$previous = $root;
		foreach ($below as $k => $node) {
			self::assertSame($people[$k], $node->person, 'the child first');
			self::assertSame(-1 - $k, $node->i);
			self::assertSame($root->x, $node->x, 'on the trunk');
			self::assertEqualsWithDelta([$root->w * .8, $root->h * .8], [$node->w, $node->h], 1e-9, 'smaller ribbons');
			self::assertTrue($node->ribbon);
			// Clear of the plaque above, whose tails hang a little below it (PaintedNode::covers()).
			self::assertGreaterThan($previous->y + $previous->h * .7, $node->y - $node->h * .5);
			$previous = $node;
		}
		// Below the last one the trunk still shows above the mound, where the roots spread.
		self::assertLessThan(500 * (1 - PaintedLayout::GroundHeight) - $unit * .5, $previous->y + $previous->h * .7);
		self::assertSame([], $layout->descendants($nodes, [], $unit));
		self::assertSame([], $layout->descendants([], $people, $unit), 'nobody to stand below');
	}

	public function testPlaquesAreRibbonsThenFlatOvalsThenTallOvals(): void
	{
		$layout = new PaintedLayout(new PaintedSpread());

		// Rows 100 high. With 200 to the nearest neighbour a plaque is a ribbon (width at most 4.6 × its height).
		self::assertEqualsWithDelta([165.6, 36.0, true], $layout->plaqueSize(200, 800, 100), 1e-9);
		self::assertEqualsWithDelta([94.0, 36.0, true], $layout->plaqueSize(100, 800, 100), 1e-9);
		// Too narrow for a ribbon, wide enough for the name across: an oval about two thirds as high as wide …
		self::assertEqualsWithDelta([81.0, 53.46, false], $layout->plaqueSize(90, 720, 100), 1e-9);
		// … but never lower than a ribbon,
		self::assertEqualsWithDelta([54.0, 36.0, false], $layout->plaqueSize(60, 480, 100), 1e-9);
		self::assertEqualsWithDelta([45.0, 36.0, false], $layout->plaqueSize(50, 800, 100), 1e-9);
		// and a tall one where the name has to be written along it.
		self::assertEqualsWithDelta([40.5, 56.0, false], $layout->plaqueSize(45, 720, 100), 1e-9);
		self::assertEqualsWithDelta([22.5, 56.0, false], $layout->plaqueSize(25, 800, 100), 1e-9);
	}

	public function testLayoutKeepsPlaquesApartAndMeasuresTheWood(): void
	{
		$nodes = new PaintedLayout(new PaintedSpread())->nodes($this->demoPlaces(), 800, 500, new PosterRandom('layout'));

		foreach ($nodes as $g => $row) {
			$previous = null;
			foreach ($row as $i => $node) {
				// On the sheet, right of the previous plaque of the row, and the same size as it.
				self::assertGreaterThanOrEqual(0, $node->x - $node->w / 2);
				self::assertLessThanOrEqual(800, $node->x + $node->w / 2);
				if ($previous !== null) {
					self::assertLessThan($i, $previous->i);
					self::assertLessThanOrEqual($node->x - $node->w / 2, $previous->x + $previous->w / 2, 'plaques of a row never overlap');
					self::assertSame([$previous->w, $previous->h, $previous->ribbon], [$node->w, $node->h, $node->ribbon]);
				}
				$previous = $node;

				$parents = array_filter([$nodes[$g + 1][2 * $i] ?? null, $nodes[$g + 1][2 * $i + 1] ?? null]);
				self::assertSame($parents === [], $node->tip);
				foreach ($parents as $parent) {
					self::assertSame(
						$parent->i % 2 === 0 ? -1 : 1,
						$parent->x <=> $node->x,
						'the father to the left, the mother to the right',
					);
					self::assertLessThan($node->y, $parent->y, 'parents are above');
					self::assertLessThanOrEqual($node->girth, $parent->girth, 'a branch is never thicker than the one that carries it');
				}
			}
		}
	}

	public function testBranchRunsFromTheChildToTheParentAndTapers(): void
	{
		$person = Fixture::dataDir();

		try {
			$config = Fixture::config($person);
			$someone = new Repository($config, new SiteDataLoader($config))->person('jan-novak-1990');
		} finally {
			Fixture::removeDir($person);
		}

		self::assertNotNull($someone);

		$child = new PaintedNode($someone, 1, 0, 100, 200, 60, 20, 8, false, true);
		$parent = new PaintedNode($someone, 2, 1, 150, 120, 40, 20, 5, true, true);
		$limbs = new PaintedLimbs(new PosterBrush());
		$limb = $limbs->branch($child, $parent, new PosterRandom('branch'));

		self::assertEqualsWithDelta(200.0, $limb->at(0)[1], .01);
		self::assertTrue($child->covers(...$limb->at(0)), 'it starts under the child\'s ribbon');
		self::assertEqualsWithDelta(150.0, $limb->at(1)[0], .01);
		self::assertEqualsWithDelta(120.0, $limb->at(1)[1], .01);
		// The wood arriving at the child is 8 wide, the parent's 5: a little swell at the fork, then 5 at the parent.
		self::assertEqualsWithDelta(5.5, $limb->widthAt(0), 1e-9);
		self::assertEqualsWithDelta(5.0, $limb->widthAt(1), 1e-9);
		self::assertEqualsWithDelta(100 + (8 - 5.5) / 2, $limb->at(0)[0], 1e-9, 'its outer edge on the edge of the wood below');
		self::assertEqualsWithDelta(-90, $limb->angleAt(0), 25, 'it leaves upward');
		self::assertLessThan(0, $limb->angleAt(1), 'it arrives from below');
		self::assertNotEquals(
			$limb,
			$limbs->branch($child, $parent, new PosterRandom('another branch')),
			'every branch is different',
		);
		self::assertEquals($limb, $limbs->branch($child, $parent, new PosterRandom('branch')));

		$space = new PaintedSpace([1 => [0 => $child], 2 => [1 => $parent]], 400, 300, 80, 240, 100, 50);
		self::assertFalse($space->free(100, 200), 'on the child\'s ribbon');
		self::assertFalse($space->free(150, 125), 'on the parent\'s ribbon');
		self::assertTrue($space->free(100, 160), 'between the rows');
		self::assertFalse($space->free(-1, 160));
		self::assertFalse($space->free(100, 301));
		self::assertFalse($space->free(350, 280), 'the corner of the legend');
		self::assertTrue($space->free(50, 280), 'the other bottom corner');

		$left = new PaintedSpace([], 400, 300, 80, 240, 100, 50, true);
		self::assertFalse($left->free(50, 280), 'the corner of the legend on the left');
		self::assertTrue($left->free(350, 280));
	}

	public function testForksContinueTheWoodBelow(): void
	{
		$nodes = new PaintedLayout(new PaintedSpread())->nodes($this->demoPlaces(), 800, 500, new PosterRandom('forks'));
		$limbs = new PaintedLimbs(new PosterBrush());

		$forks = [];
		foreach ($nodes as $g => $row) {
			foreach ($row as $i => $node) {
				$starts = [];
				foreach ([2 * $i, 2 * $i + 1] as $k) {
					$parent = $nodes[$g + 1][$k] ?? null;
					if ($parent === null) {
						continue;
					}

					$limb = $limbs->branch($node, $parent, new PosterRandom('fork' . $g . '.' . $k));
					[$x, $y] = $limb->at(0);
					$starts[] = $x;
					self::assertSame($node->y, $y);
					self::assertLessThanOrEqual($node->girth + 1e-9, $limb->widthAt(0), 'never thicker than the wood it grows from');
					self::assertLessThanOrEqual($node->girth / 2 + 1e-9, abs($x - $node->x) + $limb->widthAt(0) / 2, 'inside that wood');
					self::assertLessThanOrEqual($node->w / 2, abs($x - $node->x) + $limb->widthAt(0) / 2, 'its end is behind the plaque');
					self::assertEqualsWithDelta(-90, $limb->angleAt(0), 30, 'it leaves upward');
					// The straight run up to where the branches part is hidden, behind a ribbon and an oval alike.
					[$topX, $topY] = $limb->spine[1];
					self::assertSame($x, $topX, 'straight up');
					self::assertSame($limb->widthAt(0), $limb->widths[1]);
					self::assertTrue($node->covers($topX - $limb->widths[1] / 2, $topY), 'the fork is behind the plaque');
					self::assertTrue($node->covers($topX + $limb->widths[1] / 2, $topY), 'the fork is behind the plaque');
					self::assertLessThanOrEqual(max($limb->widthAt(0), $parent->girth) * 1.08, $limb->thickness());
					self::assertEqualsWithDelta($parent->girth, $limb->widthAt(1), 1e-9, 'the next fork starts from this width');
				}
				if (count($starts) !== 2) {
					continue;
				}

				$forks[$g . '.' . $i] = true;
				self::assertLessThanOrEqual($node->x, $starts[0], 'the father\'s branch on the left');
				self::assertGreaterThanOrEqual($node->x, $starts[1]);
			}
		}
		self::assertGreaterThan(10, count($forks));
	}

	public function testMeadowFlowersOnTheMoundOffTheTrunkAndTheLegend(): void
	{
		// The mound of A0 portrait: its middle at 400, 280 to each side, 78 high; the trunk at 380, the legend's
		// corner from 600 to the right and 120 up, so that it cuts into the mound's right foot.
		[$cx, $bottom, $half, $rise, $trunk, $clear] = [400.0, 1100.0, 280.0, 78.0, 380.0, 40.0];
		$space = new PaintedSpace([], 841, 1100, 100, 900, 841 - 600, 120);
		$meadow = new PaintedMeadow(new PosterBrush());
		$draw = static fn (PosterRandom $random): string => $meadow->draw(
			$cx,
			$bottom,
			$half,
			$rise,
			$trunk,
			$clear,
			$space,
			PosterStyle::Historical,
			$random,
		);
		$svg = $draw(new PosterRandom('meadow'));

		self::assertSame($svg, $draw(new PosterRandom('meadow')));
		self::assertNotSame($svg, $draw(new PosterRandom('another meadow')));
		self::assertLessThan(120000, strlen($svg), 'it does not swell the file');
		self::assertNotFalse(simplexml_load_string('<svg xmlns="http://www.w3.org/2000/svg">' . $svg . '</svg>'));

		preg_match_all('/<g transform="translate\(([\d.]+),([\d.]+)\)">/', $svg, $places, PREG_SET_ORDER);
		self::assertGreaterThan(80, count($places));
		$size = $rise * .085;
		foreach ($places as [, $x, $y]) {
			[$x, $y] = [(float) $x, (float) $y];
			// The ridge is never lower than .9 of the mound's height (PaintedWood::ground).
			$ridge = $bottom - $rise * .9 * PaintedMeadow::hill(($x - $cx + $half) / (2 * $half));
			self::assertGreaterThanOrEqual($ridge, $y - $size * 1.3 + .01, 'tufts of grass under the ridge');
			self::assertLessThanOrEqual($bottom, $y + $size * .65 - .01, 'the petals above the bottom edge');
			self::assertGreaterThanOrEqual($clear, abs($x - $trunk), 'off the trunk');
			self::assertFalse($x > 600 && $y > 1100 - 120, 'off the corner of the legend');
		}
		foreach (['#', 'circle', 'stroke-linecap'] as $part) {
			self::assertStringContainsString($part, $svg, 'flowers, their hearts and tufts of grass');
		}

		self::assertSame(0.0, PaintedMeadow::hill(0));
		self::assertSame(1.0, PaintedMeadow::hill(.5));
		self::assertSame(0.0, PaintedMeadow::hill(1.2));
	}

	public function testOnlyThickWoodIsHatched(): void
	{
		$wood = new PaintedWood(new PosterBrush());
		$spine = [[100.0, 200.0]];
		for ($k = 1; $k <= 10; $k++) {
			$spine[] = [100.0, 200.0 - $k * 10];
		}
		$limb = new PaintedLimb($spine, array_fill(0, 11, 12.0));

		$thick = $wood->limb($limb, PosterStyle::Historical, new PosterRandom('wood'), 3);
		self::assertSame(1, substr_count($thick, 'class="pst-hatch"'));
		preg_match('/class="pst-hatch" d="([^"]+)"/', $thick, $hatch);
		preg_match_all('/M([\d.]+),[\d.]+ L([\d.]+),[\d.]+/', $hatch[1] ?? '', $strokes, PREG_SET_ORDER);
		self::assertGreaterThan(40, count($strokes), 'strokes about a fifth of the width apart along the limb');
		foreach ($strokes as [, $from, $to]) {
			// Going up, the shaded side is on the right: from its edge inwards, never across the middle.
			self::assertGreaterThan(104, (float) $from);
			self::assertLessThanOrEqual(106, (float) $from);
			self::assertLessThan((float) $from, (float) $to);
			self::assertGreaterThan(101, (float) $to);
		}

		self::assertStringNotContainsString(
			'pst-hatch',
			$wood->limb($limb, PosterStyle::Historical, new PosterRandom('wood'), 5),
			'thin wood',
		);
	}

	/** @return array<int, array<int, ?Person>> places of six generations of the demo family */
	private function demoPlaces(): array
	{
		$dir = Fixture::dataDir(DemoFamily::siteData());

		try {
			$config = Fixture::config($dir);
			$repository = new Repository($config, new SiteDataLoader($config));

			return new PosterPeople($repository)->places($repository->ancestorGenerations('marek-skopal', 6));
		} finally {
			Fixture::removeDir($dir);
		}
	}
}
