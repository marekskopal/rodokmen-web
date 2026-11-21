<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Data\Stories;
use Rodokmen\Site\Image\Thumbnails;
use Rodokmen\Site\Image\WebpResizer;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\FanLabels;
use Rodokmen\Site\View\FanYears;
use Rodokmen\Site\View\Poster\BoxText;
use Rodokmen\Site\View\Poster\PosterBoxText;
use Rodokmen\Site\View\Poster\PosterOvalText;
use Rodokmen\Site\View\Poster\PosterPeople;
use Rodokmen\Site\View\Poster\PosterStyle;
use Rodokmen\Site\View\Poster\PosterTextWidth;
use Rodokmen\Site\View\View;
use const PREG_SET_ORDER;

/** Names in the oval plaques of the painted tree: as large as the oval allows, never over its rim. */
final class PosterOvalTextTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private PosterBoxText $box;

	private PosterOvalText $text;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
		$view = new View($config, $this->repository, new Stories($this->repository), new Thumbnails($config, new WebpResizer()));
		$geometry = new FanGeometry();
		$labels = new FanLabels($view, $geometry);
		$this->box = new PosterBoxText($view, $geometry, $labels, new FanYears());
		$this->text = new PosterOvalText(
			$view,
			$labels,
			new FanYears(),
			$this->box,
			new PosterTextWidth(),
			new PosterPeople($this->repository),
		);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testTallOvalsSplitTheNameForLargerLetters(): void
	{
		$people = [$this->person('antonin-novak-2024'), $this->person('jan-novak-1990')];

		// A tall narrow oval: the given names over the surname, over the years.
		$tall = $this->text->fit($people, PosterStyle::Simple, 27, 43, 20, .84, .78);
		self::assertTrue($tall->split);
		self::assertGreaterThan(
			$this->box->fit($people, 27 * .76, 43 * .66, 20)->size * 1.3,
			$tall->size,
			'much larger than in the box inside the oval',
		);
		self::assertSame(
			20.0,
			$this->text->fit($people, PosterStyle::Simple, 270, 430, 20, .84, .78)->size,
			'never above the largest size',
		);

		// A long low oval writes across it, on fewer lines.
		$wide = $this->text->fit($people, PosterStyle::Simple, 120, 30, 20, .84, .78);
		self::assertFalse($wide->turned);
		self::assertFalse($wide->split);
	}

	public function testSplitNameStaysOneTextInsideTheOval(): void
	{
		$person = $this->person('antonin-novak-2024');
		$text = $this->text->fit([$person], PosterStyle::Simple, 27, 43, 20, .84, .78);
		$svg = $this->text->write($person, '', 0, 0, $text);

		self::assertSame(1, substr_count($svg, '<text class="pst-t pst-name"'), 'the name is one text');
		self::assertStringContainsString('>Antonín</tspan> <tspan', $svg, 'read with a space between its lines');
		self::assertStringContainsString('>Novák</tspan>', $svg);

		// Every line's corners inside the plaque's inner ring (.88 of its width, .82 of its height); a capital
		// with its accent stands about .78 of the size above the baseline.
		[$along, $across] = $text->turned ? [21.5 * .82, 13.5 * .88] : [13.5 * .88, 21.5 * .82];
		preg_match_all(
			'/<(?:tspan x="0"|text class="pst-t") y="(-?[\d.]+)"(?: text-anchor="middle" font-size="([\d.]+)")?>([^<]+)</',
			$svg,
			$lines,
			PREG_SET_ORDER,
		);
		self::assertCount(3, $lines);
		foreach ($lines as [, $y, $size, $line]) {
			$letters = $size === '' ? $text->size : (float) $size;
			$half = mb_strlen($line) * $letters * FanGeometry::GlyphWidth / 2;
			foreach ([(float) $y - $letters * .78, (float) $y + $letters * .2] as $edge) {
				self::assertLessThanOrEqual(1.0, ($half / $along) ** 2 + ($edge / $across) ** 2, $line . ' inside the rim');
			}
		}
	}

	public function testSplitKeepsTheBirthSurnameTogetherAndMovesNotesToTheYears(): void
	{
		$text = new BoxText(10.0, true, false, true);
		$lines = static fn (string $svg): array => preg_match_all('/<tspan x="0" y="[^"]+">([^<]+)</', $svg, $m) > 0 ? $m[1] : [];
		$base = $this->person('antonin-novak-2024');

		$svg = $this->text->write($this->renamed($base, 'Marie Nováková roz. Malá'), '', 0, 0, $text);
		self::assertSame(['Marie Nováková', 'roz. Malá'], $lines($svg));
		$svg = $this->text->write($this->renamed($base, 'Jan Josef Novák (?)'), '', 0, 0, $text);
		self::assertSame(['Jan Josef', 'Novák'], $lines($svg), 'the note takes less room by the years');
		self::assertMatchesRegularExpression('/>[^<]*\d[^<]* \(\?\)<\/text>/', $svg);
		$svg = $this->text->write($this->renamed($base, 'Novák (?)'), '', 0, 0, $text);
		self::assertSame([], $lines($svg), 'nowhere to break: written whole');
		self::assertStringContainsString('>Novák (?)</text>', $svg);
	}

	public function testGaramondIsMeasuredLetterByLetter(): void
	{
		$widths = new PosterTextWidth();

		// The simple style leaves the font to the computer: the average letter.
		self::assertEqualsWithDelta(FanGeometry::GlyphWidth * 12, $widths->of('Vyprachtická', PosterStyle::Simple), 1e-9);
		// EB Garamond: narrow letters narrow, capitals and m wide; a usual name narrower than the average.
		$name = $widths->of('Vyprachtická', PosterStyle::Historical);
		self::assertLessThan(FanGeometry::GlyphWidth * 12, $name);
		self::assertGreaterThan(.45 * 12, $name, 'not narrower than measured');
		self::assertLessThan($widths->of('Mm', PosterStyle::Historical), $widths->of('il', PosterStyle::Historical) * 2);
		self::assertGreaterThan($name, $widths->of('Vyprachtická', PosterStyle::Historical, true), 'the italics are wider');
		self::assertEqualsWithDelta(.8 * 1.02, $widths->of('ß', PosterStyle::Historical), 1e-9, 'an unknown letter is wide');
	}

	private function renamed(Person $person, string $name): Person
	{
		return new ReflectionClass(Person::class)->newInstanceArgs(['name' => $name] + get_object_vars($person));
	}

	private function person(string $slug): Person
	{
		$person = $this->repository->person($slug);
		self::assertNotNull($person);

		return $person;
	}
}
