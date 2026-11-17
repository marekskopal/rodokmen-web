<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\Poster\PosterFonts;

final class PosterFontsTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/rodokmen-fonts-' . bin2hex(random_bytes(4));
		mkdir($this->root . '/public/assets/vendor/@fontsource/eb-garamond', 0777, true);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->root);
	}

	public function testLinksTheFontFilesOnTheWebsite(): void
	{
		$css = $this->fonts()->css(false);

		self::assertSame(8, substr_count($css, '@font-face'));
		self::assertStringContainsString('src:url("/assets/vendor/@fontsource/eb-garamond/eb-garamond-latin-ext-600-italic.woff2")', $css);
		self::assertStringContainsString('font-style:italic;font-weight:600', $css);
		self::assertStringContainsString('unicode-range:U+0100-02BA', $css, 'Czech letters with háček');
	}

	public function testEmbedsTheBuiltFontFiles(): void
	{
		$dir = $this->root . '/public/assets/vendor/@fontsource/eb-garamond/';
		file_put_contents($dir . 'eb-garamond-latin-400-normal.woff2', 'font-a');
		file_put_contents($dir . 'eb-garamond-latin-ext-400-normal.woff2', 'font-b');

		$css = $this->fonts()->css(true);

		self::assertSame(2, substr_count($css, '@font-face'), 'files that were not built are left out');
		self::assertStringContainsString('src:url("data:font/woff2;base64,' . base64_encode('font-a') . '")', $css);
		self::assertStringContainsString('src:url("data:font/woff2;base64,' . base64_encode('font-b') . '")', $css);
		self::assertStringNotContainsString('/assets/', $css);
	}

	private function fonts(): PosterFonts
	{
		return new PosterFonts(Fixture::config($this->root . '/data', rootDir: $this->root));
	}
}
