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
use Rodokmen\Site\View\Poster\PaintedLimbs;
use Rodokmen\Site\View\Poster\PaintedNode;
use Rodokmen\Site\View\Poster\PaintedSpace;
use Rodokmen\Site\View\Poster\PosterBrush;
use Rodokmen\Site\View\Poster\PosterPeople;
use Rodokmen\Site\View\Poster\PosterRandom;
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
		$layout = new PaintedLayout();
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
		self::assertSame(400.0, $root->x, 'the person stands in the middle');
		self::assertTrue($root->ribbon);
		self::assertFalse($root->tip);
		self::assertEqualsWithDelta($layout->trunk(800, $layout->unit(7, 500)), $root->girth, 1e-9);
	}

	public function testLayoutKeepsPlaquesApartAndMeasuresTheWood(): void
	{
		$nodes = new PaintedLayout()->nodes($this->demoPlaces(), 800, 500, new PosterRandom('layout'));

		$ovals = 0;
		foreach ($nodes as $g => $row) {
			$slot = 800 / 2 ** $g;
			foreach ($row as $i => $node) {
				$ovals += $node->ribbon ? 0 : 1;
				// Inside its own share of the width, so plaques of a row never overlap.
				self::assertGreaterThanOrEqual($i * $slot, $node->x - $node->w / 2);
				self::assertLessThanOrEqual(($i + 1) * $slot, $node->x + $node->w / 2);
				$parents = array_filter([$nodes[$g + 1][2 * $i] ?? null, $nodes[$g + 1][2 * $i + 1] ?? null]);
				self::assertSame($parents === [], $node->tip);
				foreach ($parents as $parent) {
					self::assertLessThan($node->y, $parent->y, 'parents are above');
					self::assertLessThanOrEqual($node->girth, $parent->girth, 'a branch is never thicker than the one that carries it');
				}
			}
		}
		self::assertGreaterThan(0, $ovals, 'the narrow top generations fall back to ovals');
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
		$limb = $limbs->branch($child, $parent, 6, 4, new PosterRandom('branch'));

		self::assertEqualsWithDelta(200.0, $limb->at(0)[1], .01);
		self::assertTrue($child->covers(...$limb->at(0)), 'it starts under the child\'s ribbon');
		self::assertEqualsWithDelta(150.0, $limb->at(1)[0], .01);
		self::assertEqualsWithDelta(120.0, $limb->at(1)[1], .01);
		self::assertGreaterThan($limb->widthAt(1), $limb->widthAt(0), 'widest at the fork');
		self::assertLessThan(0, $limb->angleAt(1), 'it arrives from below');
		self::assertNotEquals(
			$limb,
			$limbs->branch($child, $parent, 6, 4, new PosterRandom('another branch')),
			'every branch is different',
		);
		self::assertEquals($limb, $limbs->branch($child, $parent, 6, 4, new PosterRandom('branch')));

		$space = new PaintedSpace([1 => [0 => $child], 2 => [1 => $parent]], 400, 300, 80, 240, 100, 50);
		self::assertFalse($space->free(100, 200), 'on the child\'s ribbon');
		self::assertFalse($space->free(150, 125), 'on the parent\'s ribbon');
		self::assertTrue($space->free(100, 160), 'between the rows');
		self::assertFalse($space->free(-1, 160));
		self::assertFalse($space->free(100, 301));
		self::assertFalse($space->free(350, 280), 'the corner of the legend');
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
