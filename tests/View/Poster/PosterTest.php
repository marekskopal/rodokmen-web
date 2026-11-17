<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Image\Thumbnails;
use Rodokmen\Site\Image\WebpResizer;
use Rodokmen\Site\Tests\Support\DemoFamily;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\FanLabels;
use Rodokmen\Site\View\FanYears;
use Rodokmen\Site\View\Poster\Orientation;
use Rodokmen\Site\View\Poster\PaperFormat;
use Rodokmen\Site\View\Poster\Poster;
use Rodokmen\Site\View\Poster\PosterFan;
use Rodokmen\Site\View\Poster\PosterLayout;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterStyle;
use Rodokmen\Site\View\View;
use const PREG_SET_ORDER;

final class PosterTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private Poster $poster;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		[$this->repository, $this->poster] = self::poster($this->dir);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testAvailableDepthCountsKnownGenerations(): void
	{
		self::assertSame(2, $this->poster->availableDepth($this->root()));
		self::assertSame(1, $this->poster->availableDepth($this->person('jan-novak-1990')));
		self::assertSame(0, $this->poster->availableDepth($this->person('josef-novak-1960')));
	}

	public function testDrawsTheWholeSheetInMillimetres(): void
	{
		$svg = $this->poster->render($this->root(), $this->options(PosterLayout::Fan))->svg;

		self::assertStringStartsWith(
			'<svg xmlns="http://www.w3.org/2000/svg" class="poster-svg" viewBox="0 0 841 594" width="841mm" height="594mm"',
			$svg,
		);
		self::assertNotFalse(simplexml_load_string($svg), 'well-formed SVG');
		foreach (['Antonín Novák', 'Jan Novák', 'Marie Malá', 'Josef Novák', 'NOVÁKOVI', '1990–?'] as $text) {
			self::assertStringContainsString($text, $svg);
		}
		// Jan's mother and both parents of Marie are not known yet.
		self::assertSame(3, substr_count($svg, 'fill="#e2d9c8"'));
		self::assertStringContainsString('>Rodokmen Testových</text>', $svg, 'the site title is the default heading');
		self::assertStringContainsString('Předci osoby Antonín Novák · 2 generace', $svg);
		self::assertStringContainsString('>rod Novákovi</text>', $svg, 'legend of the drawn lines');
		self::assertStringContainsString('fill="#336699"', $svg);
	}

	public function testTitleIsEscaped(): void
	{
		$options = new PosterOptions(
			PosterLayout::Fan,
			PaperFormat::A3,
			Orientation::Portrait,
			2,
			PosterStyle::Simple,
			'Rod <Nováků> & spol.',
		);
		$svg = $this->poster->render($this->root(), $options)->svg;

		self::assertStringContainsString('>Rod &lt;Nováků&gt; &amp; spol.</text>', $svg);
		self::assertStringContainsString('viewBox="0 0 297 420"', $svg);
	}

	public function testDepthLimitsGenerations(): void
	{
		$svg = $this->poster->render($this->root(), $this->options(PosterLayout::Fan, 1))->svg;

		self::assertStringNotContainsString('Josef Novák', $svg);
		self::assertSame(0, substr_count($svg, 'fill="#e2d9c8"'));
	}

	/** @return iterable<string, array{PosterLayout, PaperFormat, Orientation}> */
	public static function sheets(): iterable
	{
		foreach (PosterLayout::cases() as $layout) {
			foreach ([PaperFormat::A4, PaperFormat::A0] as $format) {
				foreach (Orientation::cases() as $orientation) {
					yield $layout->value . ' ' . $format->value . ' ' . $orientation->value => [$layout, $format, $orientation];
				}
			}
		}
	}

	#[DataProvider('sheets')]
	public function testChartStaysOnTheSheet(PosterLayout $layout, PaperFormat $format, Orientation $orientation): void
	{
		$options = new PosterOptions($layout, $format, $orientation, 2, PosterStyle::Simple, '');
		$svg = $this->poster->render($this->root(), $options)->svg;

		self::assertSame(1, preg_match('/<g transform="translate\(([\d.]+),([\d.]+)\)">(.*?)<\/g>/', $svg, $chart));
		[$cx, $cy] = [(float) $chart[1], (float) $chart[2]];
		// Points of the ring segments (M x,y … A rx,ry 0 large sweep x,y … L x,y)
		preg_match_all('/(?:[ML]|\d \d )(-?[\d.]+),(-?[\d.]+)/', $chart[3], $points, PREG_SET_ORDER);
		self::assertGreaterThan(0, count($points));
		foreach ($points as [, $x, $y]) {
			self::assertGreaterThanOrEqual(0, $cx + (float) $x);
			self::assertLessThanOrEqual($options->width(), $cx + (float) $x);
			self::assertGreaterThanOrEqual(0, $cy + (float) $y);
			self::assertLessThanOrEqual($options->height(), $cy + (float) $y);
			if ($layout === PosterLayout::HalfFan) {
				self::assertLessThanOrEqual(.05, (float) $y, 'the half fan stays above its diameter');
			}
		}
	}

	public function testReportsTheSmallestLetters(): void
	{
		$large = $this->poster->render($this->root(), $this->options(PosterLayout::Fan))->smallestPoints();
		$options = new PosterOptions(PosterLayout::Fan, PaperFormat::A4, Orientation::Portrait, 2, PosterStyle::Simple, '');
		$small = $this->poster->render($this->root(), $options)->smallestPoints();

		self::assertGreaterThan(Poster::ReadablePoints, $small);
		self::assertGreaterThan($small, $large);
	}

	public function testReadableDepthKeepsTheLettersReadable(): void
	{
		$options = new PosterOptions(PosterLayout::HalfFan, PaperFormat::A4, Orientation::Portrait, 2, PosterStyle::Simple, '');

		self::assertSame(2, $this->poster->readable($this->root(), $options)->depth);
		self::assertSame(1, $this->poster->readable($this->root(), $options->withDepth(1))->depth);
		self::assertSame(0, $this->poster->readable($this->person('josef-novak-1960'), $options->withDepth(0))->depth);
	}

	public function testDeepTreeOnSmallPaperGetsFewerGenerations(): void
	{
		$dir = Fixture::dataDir(DemoFamily::siteData());

		try {
			[$repository, $poster] = self::poster($dir);
			$root = $repository->person('marek-skopal');
			self::assertNotNull($root);
			$available = $poster->availableDepth($root);
			$options = new PosterOptions(PosterLayout::Fan, PaperFormat::A4, Orientation::Portrait, $available, PosterStyle::Simple, '');
			$sheet = $poster->readable($root, $options);
			$tooDeep = $poster->render($root, $options->withDepth($sheet->depth + 1));
		} finally {
			Fixture::removeDir($dir);
		}

		self::assertLessThan($available, $sheet->depth);
		self::assertGreaterThanOrEqual(Poster::ReadablePoints, $sheet->smallestPoints());
		self::assertLessThan(Poster::ReadablePoints, $tooDeep->smallestPoints());
	}

	public function testLongTitleNeitherShrinksTheReadableDepthNorRunsIntoTheLegend(): void
	{
		$long = str_repeat('Rod Nováků z Hané ', 6);
		$options = new PosterOptions(PosterLayout::HalfFan, PaperFormat::A4, Orientation::Landscape, 2, PosterStyle::Simple, $long);
		$sheet = $this->poster->render($this->root(), $options);

		$untitled = new PosterOptions(PosterLayout::HalfFan, PaperFormat::A4, Orientation::Landscape, 2, PosterStyle::Simple, '');
		self::assertSame($this->poster->render($this->root(), $untitled)->smallestFont, $sheet->smallestFont);
		self::assertSame(2, $this->poster->readable($this->root(), $options)->depth);

		self::assertSame(
			1,
			preg_match('/<text class="pst-t pst-title" x="([\d.]+)" y="[\d.]+" font-size="([\d.]+)">/', $sheet->svg, $title),
		);
		$titleEnd = (float) $title[1] + mb_strlen(trim($long)) * FanGeometry::GlyphWidth * (float) $title[2];
		preg_match_all(
			'/<text class="[^"]*" x="([\d.]+)" y="[\d.]+" font-size="([\d.]+)" text-anchor="end">([^<]*)</',
			$sheet->svg,
			$rows,
			PREG_SET_ORDER,
		);
		self::assertNotSame([], $rows);
		foreach ($rows as [, $right, $size, $text]) {
			self::assertLessThan(
				(float) $right - mb_strlen(html_entity_decode($text)) * FanGeometry::GlyphWidth * (float) $size,
				$titleEnd,
				$text,
			);
		}
	}

	#[DataProvider('layouts')]
	public function testTextAlongTheRadiusIsNeverUpsideDown(PosterLayout $layout): void
	{
		// The demo family has enough generations for text along the radius.
		$dir = Fixture::dataDir(DemoFamily::siteData());

		try {
			[$repository, $poster] = self::poster($dir);
			$root = $repository->person('marek-skopal');
			self::assertNotNull($root);
			$svg = $poster->render($root, $this->options($layout, 7))->svg;
		} finally {
			Fixture::removeDir($dir);
		}

		preg_match_all('/rotate\((-?[\d.]+)\)/', $svg, $rotations);
		self::assertGreaterThan(0, count($rotations[1]));
		foreach ($rotations[1] as $angle) {
			// Normalised to -180 … 180°; upright text is turned by at most a quarter.
			$normalised = fmod((float) $angle + 540, 360) - 180;
			self::assertLessThanOrEqual(90.01, abs($normalised), 'rotate(' . $angle . ')');
		}
	}

	/** @return iterable<string, array{PosterLayout}> */
	public static function layouts(): iterable
	{
		foreach (PosterLayout::cases() as $layout) {
			yield $layout->value => [$layout];
		}
	}

	/** @return array{Repository, Poster} */
	private static function poster(string $dir): array
	{
		$config = Fixture::config($dir);
		$repository = new Repository($config, new SiteDataLoader($config));
		$view = new View($config, $repository, new Thumbnails($config, new WebpResizer()));
		$geometry = new FanGeometry();
		$fan = new PosterFan($repository, $view, $geometry, new FanLabels($view, $geometry), new FanYears());

		return [$repository, new Poster($repository, $view, $fan, $geometry)];
	}

	private function options(PosterLayout $layout, int $depth = 2): PosterOptions
	{
		return new PosterOptions($layout, PaperFormat::A1, Orientation::Landscape, $depth, PosterStyle::Simple, '');
	}

	private function root(): Person
	{
		return $this->person('antonin-novak-2024');
	}

	private function person(string $slug): Person
	{
		$person = $this->repository->person($slug);
		self::assertNotNull($person);

		return $person;
	}
}
