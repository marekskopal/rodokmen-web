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
use Rodokmen\Site\View\Poster\PaintedSpread;
use Rodokmen\Site\View\Poster\PosterPeople;
use Rodokmen\Site\View\Poster\PosterRandom;

/** Across the width, the painted tree keeps places only for the ancestors who are known. */
final class PaintedSpreadTest extends TestCase
{
	public function testSpreadPushesTheKnownAncestorsTogether(): void
	{
		$spread = new PaintedSpread();
		$someone = $this->someone();

		self::assertSame([[], -.5, .5], $spread->spread([]));
		self::assertSame([[0 => [0 => 0.0]], -.5, .5], $spread->spread([[$someone]]));

		// Both parents: side by side with a little room between them.
		[$positions, $left, $right] = $spread->spread([[$someone], [$someone, $someone]]);
		self::assertEqualsWithDelta([-.56, .56], $positions[1], 1e-9);
		self::assertEqualsWithDelta([-1.06, 1.06], [$left, $right], 1e-9);

		// One parent: only a little to their side, not a whole place.
		self::assertEqualsWithDelta([0 => -.3], $spread->spread([[$someone], [$someone, null]])[0][1], 1e-9);
		self::assertEqualsWithDelta([1 => .3], $spread->spread([[$someone], [null, $someone]])[0][1], 1e-9);
	}

	public function testSpreadLetsADeepLineReachOverAShortNeighbour(): void
	{
		$someone = $this->someone();
		// The mother has no known parents; the father's father has both, on the mother's side of him.
		$places = [
			[$someone],
			[$someone, $someone],
			[$someone, null, null, null],
			[$someone, $someone, null, null, null, null, null, null],
		];
		[$positions, $left, $right] = new PaintedSpread()->spread($places);

		self::assertEqualsWithDelta(-.86, $positions[2][0], 1e-9, 'the grandfather a little left of the father');
		self::assertEqualsWithDelta([-1.42, -.3], $positions[3], 1e-9);
		self::assertGreaterThan($positions[1][1] - .5, $positions[3][1] + .5, 'a great-grandmother stands partly above the mother');
		// A full tree of three generations would be eight places wide.
		self::assertEqualsWithDelta(2.98, $right - $left, 1e-9);
	}

	public function testSpreadKeepsTheOrderAndNeverOverlaps(): void
	{
		[$positions, $left, $right] = new PaintedSpread()->spread($this->demoPlaces());

		$widest = 0;
		foreach ($positions as $row) {
			$widest = max($widest, count($row));
			$previous = null;
			foreach ($row as $x) {
				self::assertGreaterThanOrEqual($left + .5 - 1e-9, $x);
				self::assertLessThanOrEqual($right - .5 + 1e-9, $x);
				self::assertTrue($previous === null || $x - $previous >= 1 - 1e-9, 'a whole plaque apart, in the order of the places');
				$previous = $x;
			}
		}
		self::assertGreaterThanOrEqual($widest, $right - $left);
		self::assertLessThan($widest * 1.5, $right - $left, 'about as wide as its fullest generation, not 2^generations');
	}

	public function testADeepLineGetsReadablePlaques(): void
	{
		// Three full generations and one line known four generations further.
		$someone = $this->someone();
		$places = [];
		for ($g = 0; $g <= 7; $g++) {
			$places[$g] = array_fill(0, 2 ** $g, $g <= 3 ? $someone : null);
			$places[$g][0] = $someone;
		}

		$nodes = new PaintedLayout(new PaintedSpread())->nodes($places, 800, 500, new PosterRandom('deep'));

		self::assertCount(1, $nodes[7]);
		// A lone line climbs to the left, father above son, however the plaques are moved off their places.
		for ($g = 4; $g <= 7; $g++) {
			self::assertLessThan($nodes[$g - 1][0]->x, $nodes[$g][0]->x);
		}
		// On the grid of a full tree its place would be 800 / 128 wide.
		self::assertGreaterThan(800 / 12, $nodes[7][0]->w);
		self::assertTrue($nodes[7][0]->ribbon);
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

	/** Any person; the layout only cares whether somebody is known. */
	private function someone(): Person
	{
		$dir = Fixture::dataDir();

		try {
			$config = Fixture::config($dir);

			return new Repository($config, new SiteDataLoader($config))->person('jan-novak-1990') ?? self::fail(
				'No such person in the fixture.',
			);
		} finally {
			Fixture::removeDir($dir);
		}
	}
}
