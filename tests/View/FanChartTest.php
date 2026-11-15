<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
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
	private string $dir;

	private Repository $repository;

	private FanChart $chart;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
		$view = new View($config, $this->repository, new Thumbnails($config, new WebpResizer()));
		$geometry = new FanGeometry();
		$this->chart = new FanChart($this->repository, $view, $geometry, new FanLabels($view, $geometry), new FanYears());
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
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
}
