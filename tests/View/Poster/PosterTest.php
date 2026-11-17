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
use Rodokmen\Site\View\Poster\PosterBoxText;
use Rodokmen\Site\View\Poster\PosterDecor;
use Rodokmen\Site\View\Poster\PosterFan;
use Rodokmen\Site\View\Poster\PosterFonts;
use Rodokmen\Site\View\Poster\PosterLayout;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterPaintedTree;
use Rodokmen\Site\View\Poster\PosterPeople;
use Rodokmen\Site\View\Poster\PosterStyle;
use Rodokmen\Site\View\Poster\PosterTree;
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

	/** @return iterable<string, array{PosterLayout, PaperFormat, Orientation}> */
	public static function treeSheets(): iterable
	{
		foreach ([PosterLayout::Tree, PosterLayout::TreeRight] as $layout) {
			foreach ([PaperFormat::A4, PaperFormat::A1] as $format) {
				foreach (Orientation::cases() as $orientation) {
					yield $layout->value . ' ' . $format->value . ' ' . $orientation->value => [$layout, $format, $orientation];
				}
			}
		}
	}

	#[DataProvider('treeSheets')]
	public function testTreeBoxesStayOnTheSheetAndDoNotOverlap(PosterLayout $layout, PaperFormat $format, Orientation $orientation): void
	{
		$options = new PosterOptions($layout, $format, $orientation, 2, PosterStyle::Simple, '');
		$svg = $this->poster->render($this->root(), $options)->svg;

		self::assertSame(1, preg_match('/<g transform="translate\(([\d.]+),([\d.]+)\)">/', $svg, $origin));
		preg_match_all('/<rect x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)" rx/', $svg, $found, PREG_SET_ORDER);
		// Antonín, his parents, Josef and three places of ancestors not found yet, all inside the moved group;
		// after them the swatches of the legend, placed on the sheet directly.
		self::assertCount(8, $found);
		$boxes = [];
		foreach ($found as $n => [, $x, $y, $w, $h]) {
			[$dx, $dy] = $n < 7 ? [(float) $origin[1], (float) $origin[2]] : [0.0, 0.0];
			$boxes[] = [$dx + (float) $x, $dy + (float) $y, (float) $w, (float) $h];
		}
		self::assertSame(3, substr_count($svg, 'stroke-dasharray'));
		foreach ($boxes as [$x, $y, $w, $h]) {
			self::assertGreaterThanOrEqual(0, $x);
			self::assertGreaterThanOrEqual(0, $y);
			self::assertLessThanOrEqual($options->width(), $x + $w);
			self::assertLessThanOrEqual($options->height(), $y + $h);
		}
		foreach ($boxes as $a => [$x1, $y1, $w1, $h1]) {
			foreach ($boxes as $b => [$x2, $y2, $w2, $h2]) {
				$apart = $x1 + $w1 <= $x2 || $x2 + $w2 <= $x1 || $y1 + $h1 <= $y2 || $y2 + $h2 <= $y1;
				self::assertTrue($a === $b || $apart, sprintf('boxes %d and %d overlap', $a, $b));
			}
		}
		self::assertStringContainsString('>Josef Novák', $svg);
		self::assertStringContainsString('>rod Novákovi</text>', $svg);
	}

	public function testTreeGenerationHasOneTextSize(): void
	{
		$options = new PosterOptions(PosterLayout::TreeRight, PaperFormat::A3, Orientation::Portrait, 2, PosterStyle::Simple, '');
		$svg = $this->poster->render($this->root(), $options)->svg;

		// Jan Novák and Marie Malá, the parents, are one generation.
		self::assertSame(1, preg_match('/font-size="([\d.]+)">Jan Novák/', $svg, $jan));
		self::assertSame(1, preg_match('/font-size="([\d.]+)">Marie Malá/', $svg, $marie));
		self::assertSame($jan[1], $marie[1]);
	}

	/** @return iterable<string, array{PosterLayout}> */
	public static function layouts(): iterable
	{
		foreach (PosterLayout::cases() as $layout) {
			yield $layout->value => [$layout];
		}
	}

	#[DataProvider('layouts')]
	public function testHistoricalStyleIsDecorated(PosterLayout $layout): void
	{
		$options = new PosterOptions($layout, PaperFormat::A2, Orientation::Landscape, 2, PosterStyle::Historical, '');
		$svg = $this->poster->render($this->root(), $options)->svg;

		self::assertNotFalse(simplexml_load_string($svg), 'well-formed SVG');
		self::assertStringContainsString('fill="url(#pst-paper)"', $svg, 'parchment');
		self::assertStringContainsString('font-family="&quot;EB Garamond&quot;', $svg);
		self::assertStringContainsString('@font-face{font-family:"EB Garamond"', $svg);
		self::assertStringContainsString('/assets/vendor/@fontsource/eb-garamond/', $svg, 'the page links the font files');
		self::assertSame(9, substr_count($svg, 'fill="#6e2417"'), 'eight diamonds of the frame and the person');
		self::assertStringContainsString('#4d687d', $svg, 'the line colour mixed with sepia');
		self::assertStringContainsString('Josef Novák', $svg);
		if ($layout === PosterLayout::Tree || $layout === PosterLayout::TreeRight) {
			// Only the two panels of the cartouche and the legend swatch are rounded; the seven boxes are labels.
			self::assertSame(3, substr_count($svg, ' rx="'));
		}

		$standalone = $this->poster->render($this->root(), $options, standalone: true)->svg;
		self::assertStringNotContainsString('/assets/vendor/', $standalone, 'the downloaded file does not link the website');
	}

	public function testSimpleStyleHasNoDecoration(): void
	{
		$svg = $this->poster->render($this->root(), $this->options(PosterLayout::Tree))->svg;

		self::assertStringNotContainsString('pst-paper', $svg);
		self::assertStringNotContainsString('@font-face', $svg);
		self::assertStringContainsString('fill="#336699"', $svg, 'the line colour as it is');
	}

	public function testPaintedTreeGrowsBranchesOnlyToKnownAncestors(): void
	{
		$options = new PosterOptions(PosterLayout::PaintedTree, PaperFormat::A2, Orientation::Portrait, 2, PosterStyle::Simple, '');
		$svg = $this->poster->render($this->root(), $options)->svg;

		self::assertNotFalse(simplexml_load_string($svg));
		// Antonín on one ellipse, his parents and grandfather Josef on two each (a light ground and the colour)
		self::assertSame(7, substr_count($svg, '<ellipse '));
		self::assertStringNotContainsString('stroke-dasharray', $svg, 'ancestors not found yet get no branch');
		self::assertSame(1, preg_match('/<g fill="#6b4a2b"[^>]*>(.*?)<\/g>/', $svg, $branches));
		self::assertSame(3, substr_count($branches[1], '<path '), 'to Jan, to Marie and from Jan to Josef');
		self::assertGreaterThan(0, substr_count($svg, 'rotate('), 'leaves');
		foreach (['Antonín Novák', 'Jan Novák', 'Marie Malá', 'Josef Novák', 'rod Novákovi'] as $text) {
			self::assertStringContainsString($text, $svg);
		}

		// Leaves and medallions stay on the sheet.
		self::assertSame(1, preg_match('/<g transform="translate\(([\d.]+),([\d.]+)\)">/', $svg, $origin));
		preg_match_all('/translate\((-?[\d.]+),(-?[\d.]+)\) rotate/', $svg, $leaves, PREG_SET_ORDER);
		foreach ($leaves as [, $x, $y]) {
			self::assertGreaterThanOrEqual(0, (float) $origin[1] + (float) $x);
			self::assertLessThanOrEqual($options->width(), (float) $origin[1] + (float) $x);
			self::assertGreaterThanOrEqual(0, (float) $y, 'not above the tree, in the band of the title');
		}
	}

	public function testPaintedTreeStaysInItsArea(): void
	{
		// A large sheet with one generation has the thickest trunk and the largest leaves.
		$options = new PosterOptions(PosterLayout::PaintedTree, PaperFormat::A0, Orientation::Landscape, 1, PosterStyle::Historical, '');
		$svg = $this->poster->render($this->root(), $options)->svg;

		self::assertSame(1, preg_match('/<g transform="translate\(([\d.]+),([\d.]+)\)">/', $svg, $origin));
		$margin = (float) $origin[1];
		$bottom = $options->height() - $margin - (float) $origin[2];
		preg_match_all('/translate\((-?[\d.]+),(-?[\d.]+)\) rotate/', $svg, $leaves, PREG_SET_ORDER);
		self::assertGreaterThan(0, count($leaves));
		foreach ($leaves as [, , $y]) {
			self::assertGreaterThanOrEqual(0, (float) $y);
		}
		// Roots: the quadratic curves at the foot of the trunk end above the bottom of the area.
		preg_match_all('/Q-?[\d.]+,-?[\d.]+ -?[\d.]+,(-?[\d.]+)/', $svg, $roots);
		self::assertGreaterThan(0, count($roots[1]));
		foreach ($roots[1] as $y) {
			self::assertLessThanOrEqual($bottom, (float) $y);
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

	#[DataProvider('fans')]
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
	public static function fans(): iterable
	{
		yield 'fan' => [PosterLayout::Fan];
		yield 'half fan' => [PosterLayout::HalfFan];
	}

	/** @return array{Repository, Poster} */
	private static function poster(string $dir): array
	{
		$config = Fixture::config($dir);
		$repository = new Repository($config, new SiteDataLoader($config));
		$view = new View($config, $repository, new Thumbnails($config, new WebpResizer()));
		$geometry = new FanGeometry();
		$labels = new FanLabels($view, $geometry);
		$people = new PosterPeople($repository);
		$fan = new PosterFan($repository, $people, $view, $geometry, $labels, new FanYears());
		$text = new PosterBoxText($view, $geometry, $labels, new FanYears());
		$tree = new PosterTree($people, $view, $text);
		$painted = new PosterPaintedTree($people, $view, $text);

		return [$repository, new Poster($repository, $view, $fan, $tree, $painted, new PosterDecor(), new PosterFonts($config), $geometry)];
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
