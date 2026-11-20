<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\View\Poster\PosterBorder;
use Rodokmen\Site\View\Poster\PosterBrush;
use Rodokmen\Site\View\Poster\PosterPigments;
use Rodokmen\Site\View\Poster\PosterRandom;
use Rodokmen\Site\View\Poster\PosterStyle;
use const PREG_SET_ORDER;

final class PosterBorderTest extends TestCase
{
	private const Width = 841;

	private const Height = 594;

	private const Margin = 29.7;

	private PosterBorder $border;

	protected function setUp(): void
	{
		$this->border = new PosterBorder(new PosterBrush());
	}

	public function testSimpleStyleHasNoBorder(): void
	{
		self::assertSame('', $this->draw(PosterStyle::Simple, 'a'));
	}

	public function testDrawsTheSameBorderForTheSameSeedOnly(): void
	{
		$border = $this->draw(PosterStyle::Historical, 'a');

		self::assertSame($border, $this->draw(PosterStyle::Historical, 'a'));
		self::assertNotSame($border, $this->draw(PosterStyle::Historical, 'b'));
	}

	public function testIsVectorOnly(): void
	{
		$border = $this->draw(PosterStyle::Historical, 'a');

		self::assertNotFalse(simplexml_load_string('<svg xmlns="http://www.w3.org/2000/svg">' . $border . '</svg>'), 'well-formed');
		self::assertStringNotContainsString('<filter', $border);
		self::assertStringNotContainsString('<image', $border);
		self::assertStringContainsString('fill="' . PosterPigments::Vermilion . '" fill-rule="evenodd"', $border, 'the band');
		self::assertSame(4, preg_match_all('/<rect [^>]*fill="' . PosterPigments::Lapis . '"/', $border), 'corner medallions');
	}

	public function testStaysInTheBandOfTheMargin(): void
	{
		// A leaf is drawn around 0,0 of its own group: only where the group stands counts.
		$border = (string) preg_replace('#(<g transform="[^"]*">).*?</g>#', '$1</g>', $this->draw(PosterStyle::Historical, 'a'));
		$outer = self::Margin * PosterBorder::Outer;
		$inner = self::Margin * PosterBorder::Inner;

		// Leaves stand at a translate(), balls and berries are circles, the rest are paths drawn from absolute points.
		preg_match_all('/(?:translate\\(|cx="|[MLC ])([\\d.]+)(?:,|" cy=")([\\d.]+)/', $border, $points, PREG_SET_ORDER);
		self::assertGreaterThan(500, count($points));
		// The far corner of every corner medallion (and of its gold rule) too.
		preg_match_all('/<rect x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)"/', $border, $rects, PREG_SET_ORDER);
		self::assertCount(8, $rects);
		foreach ($rects as [$rect, $x, $y, $w, $h]) {
			$points[] = [$rect, $x, $y];
			$points[] = [$rect, (string) ((float) $x + (float) $w), (string) ((float) $y + (float) $h)];
		}

		foreach ($points as $match) {
			[$x, $y] = [(float) $match[1], (float) $match[2]];
			// Distance from the nearest edge of the sheet.
			$edge = min($x, $y, self::Width - $x, self::Height - $y);
			// The medallions stand out of the band a little, but keep off the edge of the sheet and the title.
			self::assertGreaterThanOrEqual($outer / 2, $edge, $match[0]);
			if (!str_starts_with($match[0], '<rect')) {
				self::assertLessThanOrEqual($inner + .5, $edge, $match[0]);
			}
			self::assertLessThan(self::Margin * .7, $edge, $match[0] . ' reaches the title\'s panel');
		}
	}

	public function testTheVineEndsAtTheCornersWithWholeWaves(): void
	{
		self::assertSame(2, $this->border->halfWaves(10, 16));
		self::assertSame(10, $this->border->halfWaves(200, 16));
		self::assertSame(10, $this->border->halfWaves(205, 16));
	}

	private function draw(PosterStyle $style, string $seed): string
	{
		return $this->border->draw($style, self::Width, self::Height, self::Margin, new PosterRandom($seed));
	}
}
