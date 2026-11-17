<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\View\Poster\PosterStyle;

final class PosterStyleTest extends TestCase
{
	public function testSimpleStyleKeepsTheLineColours(): void
	{
		self::assertSame('#336699', PosterStyle::Simple->tint('#336699'));
		self::assertFalse(PosterStyle::Simple->isOrnate());
		self::assertFalse(PosterStyle::Simple->usesWebFont());
	}

	public function testHistoricalStyleMixesTheLineColoursWithSepia(): void
	{
		// 30 % of sepia (139, 107, 61): 0x33 → 77, 0x66 → 104, 0x99 → 125
		self::assertSame('#4d687d', PosterStyle::Historical->tint('#336699'));
		self::assertSame('#4d687d', PosterStyle::Historical->tint('#336699'), 'the same colour every time');
		self::assertSame('#4d687d', PosterStyle::Historical->tint('#369'), 'short notation');
		self::assertSame('#369', PosterStyle::Simple->tint('#369'));
		self::assertSame('rgb(1,2,3)', PosterStyle::Historical->tint('rgb(1,2,3)'), 'other notations stay as they are');
		self::assertTrue(PosterStyle::Historical->isOrnate());
		self::assertStringStartsWith('"EB Garamond"', PosterStyle::Historical->font());
	}
}
