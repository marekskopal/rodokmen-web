<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\Poster\Orientation;
use Rodokmen\Site\View\Poster\PaperFormat;
use Rodokmen\Site\View\Poster\Poster;
use Rodokmen\Site\View\Poster\PosterLayout;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterParts;
use Rodokmen\Site\View\Poster\PosterStyle;

/** The heading and the footer of the poster on the sides the visitor chose (PosterParts). */
final class PosterSidesTest extends TestCase
{
	/** Margin of the simple style on A1 landscape. */
	private const Margin = 594 * .035;

	private string $dir;

	private Repository $repository;

	private Poster $poster;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		[$this->repository, $this->poster] = PosterTest::poster($this->dir);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	#[DataProvider('layouts')]
	public function testPutsTheHeadingAndTheFooterOnTheChosenSides(PosterLayout $layout): void
	{
		$default = $this->sheet($layout, 'vlevo', 'vpravo');
		self::assertEqualsWithDelta(self::Margin, self::title($default), .1, 'the heading on the left');
		self::assertSame('end', self::footer($default)[2], 'the footer aligned to the right');

		$swapped = $this->sheet($layout, 'vpravo', 'vlevo');
		self::assertGreaterThan(841 / 2, self::title($swapped), 'the heading on the right');
		self::assertEqualsWithDelta(self::Margin, self::footer($swapped)[0], .1, 'the footer on the left');
		self::assertSame('start', self::footer($swapped)[2]);
	}

	public function testTheFooterGoesBelowTheHeadingOnItsSideAboveTheHalfFan(): void
	{
		$y = fn (string $heading, string $footer): float => self::footer($this->sheet(PosterLayout::HalfFan, $heading, $footer))[1];

		self::assertGreaterThan($y('vlevo', 'vpravo'), $y('vpravo', 'vpravo'));
		self::assertGreaterThan($y('vpravo', 'vlevo'), $y('vlevo', 'vlevo'));
	}

	/** @return iterable<string, array{PosterLayout}> */
	public static function layouts(): iterable
	{
		return PosterTest::layouts();
	}

	private function sheet(PosterLayout $layout, string $heading, string $footer): string
	{
		$person = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($person);
		$parts = PosterParts::fromQuery(
			['casti' => '1', 'nadpis-zobrazit' => $heading, 'paticka-zobrazit' => $footer, 'paticka-datum' => '1'],
		);

		return $this->poster->render(
			$person,
			new PosterOptions($layout, PaperFormat::A1, Orientation::Landscape, 2, PosterStyle::Simple, '', $parts),
		)->svg;
	}

	/** The x of the heading. */
	private static function title(string $svg): float
	{
		self::assertSame(1, preg_match('/<text class="pst-t pst-title" x="([\d.]+)"/', $svg, $match));

		return (float) $match[1];
	}

	/**
	 * The x, y and alignment of the footer's date.
	 *
	 * @return array{float, float, string}
	 */
	private static function footer(string $svg): array
	{
		$pattern = '/<text class="pst-t pst-muted" x="([\d.]+)" y="([\d.]+)" font-size="[\d.]+" text-anchor="(\w+)"/';
		self::assertSame(1, preg_match($pattern, $svg, $match));

		return [(float) $match[1], (float) $match[2], $match[3]];
	}
}
