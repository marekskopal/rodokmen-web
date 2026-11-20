<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\View\Poster\PosterBrush;
use Rodokmen\Site\View\Poster\PosterDecor;
use Rodokmen\Site\View\Poster\PosterRandom;
use Rodokmen\Site\View\Poster\PosterStyle;

/** The parchment of the historical poster: yellowed corners, stains and foxing, all vector and still readable. */
final class PosterDecorTest extends TestCase
{
	public function testSimplePaperIsPlainWhite(): void
	{
		self::assertSame(
			'<rect width="210" height="297" fill="#ffffff"/>',
			new PosterDecor(new PosterBrush())->paper(PosterStyle::Simple, 210, 297, new PosterRandom('paper')),
		);
	}

	public function testParchmentIsStainedTheSameWayEveryTime(): void
	{
		$decor = new PosterDecor(new PosterBrush());
		$paper = $decor->paper(PosterStyle::Historical, 1189, 841, new PosterRandom('paper|someone'));

		self::assertSame($paper, $decor->paper(PosterStyle::Historical, 1189, 841, new PosterRandom('paper|someone')));
		self::assertNotSame($paper, $decor->paper(PosterStyle::Historical, 1189, 841, new PosterRandom('paper|another')));
		self::assertNotFalse(simplexml_load_string('<svg xmlns="http://www.w3.org/2000/svg">' . $paper . '</svg>'));
		self::assertStringNotContainsString('<filter', $paper);
		self::assertStringNotContainsString('<image', $paper);

		self::assertSame(4, substr_count($paper, 'fill="url(#pst-corner)"'), 'every corner yellowed');
		$stains = preg_match_all('/fill="url\(#pst-stain\d\)"/', $paper);
		self::assertGreaterThanOrEqual(4, $stains);
		self::assertLessThanOrEqual(7, $stains);
		$spots = '/fill="(' . implode('|', PosterDecor::Foxing) . ')" fill-opacity="/';
		$foxing = preg_match_all($spots, $paper);
		self::assertGreaterThanOrEqual(10, $foxing);
		self::assertLessThanOrEqual(25, $foxing);

		// As many stains and spots on A4 as on A0: only their size follows the paper.
		$small = $decor->paper(PosterStyle::Historical, 297, 210, new PosterRandom('paper|someone'));
		self::assertSame($stains, preg_match_all('/fill="url\(#pst-stain\d\)"/', $small));
		self::assertSame($foxing, preg_match_all($spots, $small));
	}

	public function testTextStaysReadableOnTheDarkestParchment(): void
	{
		// The edge of the gradient under a yellowed corner, two stains overlapping at their middles with their tide
		// lines, and the strongest spot of foxing on top.
		$ink = self::luminance(self::rgb(PosterStyle::Historical->ink()));
		foreach (PosterDecor::Stains as $stain) {
			foreach (PosterDecor::Foxing as $spot) {
				$paper = self::over(self::rgb(PosterDecor::Edge), self::rgb(PosterDecor::Corner), PosterDecor::CornerOpacity);
				foreach ([1, 1, .6, .6] as $share) {
					$paper = self::over($paper, self::rgb($stain), PosterDecor::StainOpacity * $share);
				}
				$paper = self::over($paper, self::rgb($spot), PosterDecor::FoxingOpacity);

				self::assertGreaterThanOrEqual(
					4.5,
					(self::luminance($paper) + .05) / ($ink + .05),
					'WCAG AA on ' . $stain . ' and ' . $spot,
				);
			}
		}
	}

	/** @return array{float, float, float} */
	private static function rgb(string $hex): array
	{
		$value = (int) hexdec(ltrim($hex, '#'));

		return [($value >> 16 & 255) / 255, ($value >> 8 & 255) / 255, ($value & 255) / 255];
	}

	/**
	 * @param array{float, float, float} $below
	 * @param array{float, float, float} $above
	 * @return array{float, float, float}
	 */
	private static function over(array $below, array $above, float $opacity): array
	{
		return [
			$below[0] + ($above[0] - $below[0]) * $opacity,
			$below[1] + ($above[1] - $below[1]) * $opacity,
			$below[2] + ($above[2] - $below[2]) * $opacity,
		];
	}

	/** @param array{float, float, float} $rgb */
	private static function luminance(array $rgb): float
	{
		$linear = array_map(static fn (float $c): float => $c <= .03928 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4, $rgb);

		return .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
	}
}
