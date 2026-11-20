<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Image\Thumbnails;
use Rodokmen\Site\Image\WebpResizer;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\Poster\PosterBrush;
use Rodokmen\Site\View\Poster\PosterDecor;
use Rodokmen\Site\View\Poster\PosterRandom;
use Rodokmen\Site\View\Poster\PosterStyle;
use Rodokmen\Site\View\Poster\PosterTitle;
use Rodokmen\Site\View\View;
use const ENT_QUOTES;

final class PosterTitleTest extends TestCase
{
	private string $dir;

	private PosterTitle $title;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$view = new View($config, new Repository($config, new SiteDataLoader($config)), new Thumbnails($config, new WebpResizer()));
		$brush = new PosterBrush();
		$this->title = new PosterTitle($view, new FanGeometry(), new PosterDecor($brush), $brush);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testHistoricalHeadingOpensWithAnIlluminatedInitial(): void
	{
		$svg = $this->draw(PosterStyle::Historical, 'Rodokmen Řehořových');

		self::assertNotFalse(simplexml_load_string('<svg xmlns="http://www.w3.org/2000/svg">' . $svg . '</svg>'), 'well-formed');
		self::assertSame(1, preg_match('#<text class="pst-t pst-title"[^>]*>(.*?)</text>#', $svg, $title));
		self::assertSame('Rodokmen Řehořových', strip_tags($title[1]), 'the whole heading, the first letter once');
		self::assertStringContainsString('class="pst-initial"', $title[1]);
		self::assertSame(1, preg_match('#<tspan class="pst-initial"[^>]*>R</tspan>#', $title[1]));
		self::assertStringContainsString('class="pst-t pst-sub pst-rubric"', $svg, 'the subtitle in red');
		self::assertStringContainsString('<ellipse', $svg, 'the rolls of the scroll');
		self::assertSame($svg, $this->draw(PosterStyle::Historical, 'Rodokmen Řehořových'), 'the same every time');
	}

	public function testDiacriticsMakeAnInitialToo(): void
	{
		self::assertSame(1, preg_match('#<tspan class="pst-initial"[^>]*>Ř</tspan>#', $this->draw(PosterStyle::Historical, 'Řehořovi')));
		// Decomposed: R and a combining caron stay together in the initial.
		$svg = $this->draw(PosterStyle::Historical, "R\u{30C}ehořovi");
		self::assertSame(1, preg_match("#<tspan class=\"pst-initial\"[^>]*>R\u{30C}</tspan>#u", $svg));
		self::assertStringContainsString('>ehořovi</tspan>', $svg);
	}

	/** @return iterable<string, array{string}> */
	public static function withoutInitial(): iterable
	{
		yield 'one letter' => ['R'];
		yield 'one letter and a space' => ['R '];
		yield 'a word of one letter' => ['A Novákovi'];
		yield 'a number first' => ['1848 a rod Nováků'];
		yield 'a quote first' => ['„Novákovi“'];
		yield 'a small letter first' => ['rod Nováků'];
		yield 'empty' => [''];
	}

	#[DataProvider('withoutInitial')]
	public function testHeadingsThatDoNotStartAWordGetNoInitial(string $heading): void
	{
		$svg = $this->draw(PosterStyle::Historical, $heading);

		self::assertStringNotContainsString('pst-initial', $svg);
		self::assertSame(1, preg_match('#<text class="pst-t pst-title"[^>]*>(.*?)</text>#', $svg, $title));
		self::assertSame(htmlspecialchars($heading, ENT_QUOTES), $title[1]);
	}

	public function testSimpleStyleWritesThePlainHeading(): void
	{
		$svg = $this->draw(PosterStyle::Simple, 'Rodokmen Novákových');

		self::assertStringStartsWith('<text class="pst-t pst-title" x="20.0" y="', $svg, 'no scroll, no initial');
		self::assertStringContainsString('>Rodokmen Novákových</text>', $svg);
		self::assertStringContainsString('class="pst-t pst-sub" ', $svg);
	}

	public function testTheTextStartsBesideTheInitial(): void
	{
		$historical = $this->draw(PosterStyle::Historical, 'Rodokmen Novákových');

		self::assertSame(1, preg_match('#<text class="pst-t pst-sub pst-rubric" x="([\d.]+)"#', $historical, $sub));
		self::assertSame(1, preg_match('#<tspan x="([\d.]+)"[^>]*>odokmen Novákových</tspan>#', $historical, $rest));
		self::assertSame($sub[1], $rest[1]);
		self::assertGreaterThan(20 + 30, (float) $sub[1], 'beside the initial as tall as both lines');
	}

	private function draw(PosterStyle $style, string $heading): string
	{
		return $this->title->draw($style, $heading, 'Předci osoby Antonín Novák · 2 generace', 20, 20, 20, 500, 6, new PosterRandom('t'));
	}
}
