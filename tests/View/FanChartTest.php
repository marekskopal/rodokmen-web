<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Line;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Data\Stories;
use Rodokmen\Site\Image\Thumbnails;
use Rodokmen\Site\Image\WebpResizer;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\FanChart;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\FanLabels;
use Rodokmen\Site\View\FanYears;
use Rodokmen\Site\View\View;

final class FanChartTest extends TestCase
{
	/** @var list<string> */
	private array $dirs = [];

	private Repository $repository;

	private FanChart $chart;

	protected function setUp(): void
	{
		[$this->chart, $this->repository] = $this->chart(Fixture::siteData());
	}

	protected function tearDown(): void
	{
		foreach ($this->dirs as $dir) {
			Fixture::removeDir($dir);
		}
	}

	public function testAvailableDepthCountsKnownGenerations(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);

		self::assertSame(2, $this->chart->availableDepth($root));
	}

	public function testRendersPeopleAndEmptySlotsOfKnownChildren(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);
		$svg = $this->chart->render($root, 10);

		self::assertStringStartsWith('<svg', $svg);
		self::assertStringContainsString('class="fan-root"', $svg);
		foreach (['antonin-novak-2024', 'jan-novak-1990', 'marie-mala-1992', 'josef-novak-1960'] as $slug) {
			self::assertStringContainsString('href="/osoba/' . $slug . '"', $svg);
		}
		// Jan's mother and both parents of Marie are unknown; Josef's parents are not drawn (no known generation 3).
		self::assertSame(3, substr_count($svg, 'class="fan-empty"'));
		self::assertStringContainsString('viewBox="-670 -670 1340 1340"', $svg);
	}

	public function testDepthLimitsRings(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);
		$svg = $this->chart->render($root, 1);

		self::assertStringNotContainsString('josef-novak-1960', $svg);
		self::assertSame(0, substr_count($svg, 'class="fan-empty"'));
	}

	public function testLegendListsTheDrawnLinesAndTheEmptySlots(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);
		$legend = $this->chart->legend($root, 10);

		self::assertSame(['novakovi'], array_map(static fn (Line $line): string => $line->slug, $legend->lines));
		self::assertFalse($legend->sides);
		self::assertFalse($legend->uncertain);
		self::assertTrue($legend->unknown);
	}

	public function testLegendOfOneGenerationWithoutEmptySlots(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);

		self::assertFalse($this->chart->legend($root, 1)->unknown);
	}

	public function testUncertainAncestorsOutsideTheLinesAreHatchedAndInTheLegend(): void
	{
		$data = Fixture::siteData();
		self::assertIsArray($data['people']);
		self::assertIsArray($data['people']['marie-mala-1992']);
		$data['people']['marie-mala-1992']['st'] = 'prob';
		$data['people']['marie-mala-1992']['line'] = null;
		[$chart, $repository] = $this->chart($data);
		$root = $repository->person('antonin-novak-2024');
		self::assertNotNull($root);

		$legend = $chart->legend($root, 10);
		self::assertTrue($legend->sides);
		self::assertTrue($legend->uncertain);
		$svg = $chart->render($root, 10);
		self::assertSame(1, substr_count($svg, 'class="fan-seg-weak"'));
		self::assertStringContainsString('style="--c:' . FanChart::SideColors[1] . ';', $svg);
	}

	public function testHatchGrowsWithTheChart(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);

		self::assertLessThan($this->hatchWidth($this->chart->render($root, 10)), $this->hatchWidth($this->chart->render($root, 1)));
	}

	/** @return iterable<string, array{string, string}> */
	public static function years(): iterable
	{
		yield 'both years' => ['1746 – 1801', '1746–1801'];
		yield 'unknown death' => ['1774 – ?', '1774–?'];
		yield 'death before' => ['1791 – před 1854', '1791–?'];
		yield 'only death before' => ['? – před 1785', '† před 1785'];
		yield 'alive after' => ['? – po 1849', 'žil 1849'];
		yield 'only death' => ['? – 1682', '† 1682'];
		yield 'living' => ['* 1931', '* 1931'];
		yield 'uncertain death' => ['1848 – 1929 (?)', '1848–1929'];
		yield 'approximate' => ['asi 1700 – 1760', '≈1700–1760'];
		yield 'empty' => ['? – ?', ''];
	}

	#[DataProvider('years')]
	public function testShortYears(string $y, string $expected): void
	{
		self::assertSame($expected, $this->chart->shortYears($y));
	}

	private function hatchWidth(string $svg): int
	{
		self::assertSame(1, preg_match('/<pattern id="fan-hatch"[^>]* width="(\d+)"/', $svg, $m));

		return (int) $m[1];
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{FanChart, Repository}
	 */
	private function chart(array $data): array
	{
		$dir = Fixture::dataDir($data);
		$this->dirs[] = $dir;
		$config = Fixture::config($dir);
		$repository = new Repository($config, new SiteDataLoader($config));
		$view = new View($config, $repository, new Stories($repository), new Thumbnails($config, new WebpResizer()));
		$geometry = new FanGeometry();

		return [new FanChart($repository, $view, $geometry, new FanLabels($view, $geometry), new FanYears()), $repository];
	}
}
