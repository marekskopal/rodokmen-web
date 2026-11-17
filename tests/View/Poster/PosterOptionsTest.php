<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\View\Poster\Orientation;
use Rodokmen\Site\View\Poster\PaperFormat;
use Rodokmen\Site\View\Poster\PosterLayout;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterStyle;

final class PosterOptionsTest extends TestCase
{
	public function testDefaultsToALandscapeA1FanOfAllGenerations(): void
	{
		$options = PosterOptions::fromQuery([], 7);

		self::assertSame(PosterLayout::Fan, $options->layout);
		self::assertSame(PaperFormat::A1, $options->format);
		self::assertSame(Orientation::Landscape, $options->orientation);
		self::assertSame(7, $options->depth);
		self::assertSame('', $options->title);
		self::assertSame([841, 594], [$options->width(), $options->height()]);
		self::assertSame('poster-a1-landscape', $options->pageName());
		self::assertFalse(PosterOptions::hasDepth([]));
	}

	public function testReadsTheChoices(): void
	{
		$query = ['rozvrzeni' => 'pulvejir', 'format' => 'a3', 'orientace' => 'na-vysku', 'generace' => '4', 'nadpis' => "  Rod \n Nováků  "];
		$options = PosterOptions::fromQuery($query, 7);

		self::assertSame(PosterLayout::HalfFan, $options->layout);
		self::assertSame(PaperFormat::A3, $options->format);
		self::assertSame(Orientation::Portrait, $options->orientation);
		self::assertSame(4, $options->depth);
		self::assertSame('Rod Nováků', $options->title);
		self::assertSame([297, 420], [$options->width(), $options->height()]);
		self::assertSame('poster-a3-portrait', $options->pageName());
		self::assertTrue(PosterOptions::hasDepth($query));
	}

	public function testReadsTheTreeLayouts(): void
	{
		self::assertSame(PosterLayout::Tree, PosterOptions::fromQuery(['rozvrzeni' => 'strom'], 3)->layout);
		self::assertSame(PosterLayout::TreeRight, PosterOptions::fromQuery(['rozvrzeni' => 'strom-vpravo'], 3)->layout);
		self::assertFalse(PosterLayout::Tree->isFan());
		self::assertTrue(PosterLayout::HalfFan->isFan());
	}

	public function testInvalidValuesFallBackToDefaults(): void
	{
		$options = PosterOptions::fromQuery(
			['rozvrzeni' => 'kruh', 'format' => 'b5', 'orientace' => ['x'], 'generace' => '-3'],
			7,
		);

		self::assertSame(PosterLayout::Fan, $options->layout);
		self::assertSame(PaperFormat::A1, $options->format);
		self::assertSame(Orientation::Landscape, $options->orientation);
		self::assertSame(7, $options->depth);
	}

	public function testDepthStaysWithinTheKnownGenerations(): void
	{
		self::assertSame(7, PosterOptions::fromQuery(['generace' => '30'], 7)->depth);
		self::assertSame(1, PosterOptions::fromQuery(['generace' => '0'], 7)->depth);
		self::assertSame(0, PosterOptions::fromQuery(['generace' => '3'], 0)->depth, 'nobody above the person');
		self::assertSame(2, PosterOptions::fromQuery([], 7)->withDepth(2)->depth);
	}

	public function testTitleIsShortened(): void
	{
		$title = PosterOptions::fromQuery(['nadpis' => str_repeat('Ž', 200)], 3)->title;

		self::assertSame(PosterOptions::MaxTitleLength, mb_strlen($title));
	}

	public function testEveryPaperSizeHasACssPage(): void
	{
		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');
		foreach (PaperFormat::cases() as $format) {
			foreach (Orientation::cases() as $orientation) {
				$options = new PosterOptions(PosterLayout::Fan, $format, $orientation, 1, PosterStyle::Simple, '');
				$page = sprintf('@page %s { size: %dmm %dmm; margin: 0; }', $options->pageName(), $options->width(), $options->height());
				self::assertStringContainsString($page, $css);
				self::assertStringContainsString(sprintf('.%1$s { page: %1$s; }', $options->pageName()), $css);
			}
		}
	}
}
