<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Config;

/**
 * EB Garamond (SIL Open Font License) for the historical poster, copied into public/assets/vendor by `pnpm build`.
 * The page links the font files; the downloaded SVG carries them inside, so that it looks the same elsewhere.
 */
final readonly class PosterFonts
{
	public const Family = 'EB Garamond';

	private const Path = '/assets/vendor/@fontsource/eb-garamond/';

	/** Subsets of the font files and the characters in them (Czech letters with háček are in latin-ext). */
	private const Subsets = [
		'latin' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,'
			. 'U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
		'latin-ext' => 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,'
			. 'U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF',
	];

	/** Weights and styles the poster uses: names and titles are 600 (bold falls back to it), uncertain ones italic. */
	private const Faces = [[400, 'normal'], [400, 'italic'], [600, 'normal'], [600, 'italic']];

	public function __construct(private Config $config)
	{
	}

	/**
	 * The @font-face rules: linking the files on the website, or with the files inside (data: URLs) for a poster
	 * that leaves it. Embedded files that were not built are left out, so the poster falls back to Garamond or Times
	 * (linked ones that are missing fail to load in the browser, with the same fallback).
	 */
	public function css(bool $embed): string
	{
		$css = '';
		foreach (self::Subsets as $subset => $range) {
			foreach (self::Faces as [$weight, $style]) {
				$file = sprintf('eb-garamond-%s-%d-%s.woff2', $subset, $weight, $style);
				$url = $embed ? $this->dataUrl($file) : self::Path . $file;
				if ($url === null) {
					continue;
				}

				$css .= sprintf(
					'@font-face{font-family:"%s";font-style:%s;font-weight:%d;src:url("%s") format("woff2");unicode-range:%s}',
					self::Family,
					$style,
					$weight,
					$url,
					$range,
				);
			}
		}

		return $css;
	}

	private function dataUrl(string $file): ?string
	{
		$data = @file_get_contents($this->config->rootDir . '/public' . self::Path . $file);

		return $data === false ? null : 'data:font/woff2;base64,' . base64_encode($data);
	}
}
