<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\View\FanGeometry;

/**
 * Width of a line of text in units of its font size. The historical style writes in EB Garamond, which the poster
 * carries inside (PosterFonts), so its letters are measured one by one (the semibold of the names, upright and
 * italic, from the font's own widths, rounded up); the simple style leaves the font to the computer, so it keeps the average letter of
 * FanGeometry, which is wide enough for the usual serif fonts.
 */
final readonly class PosterTextWidth
{
	/** Letters of EB Garamond SemiBold by their width; any other is taken as wide as Default. */
	private const Garamond = [
		'.23' => ' ',
		'.29' => 'iíjlľĺł.,\'',
		'.36' => 'fsštťIÍJ():;-',
		'.39' => 'rřŕ?',
		'.43' => 'aáäcčeéězž',
		'.5' => 'goóöôvxyýSŠ',
		'.56' => 'bdďhknňpquúůü0123456789≈~',
		'.62' => 'BEÉĚFLĽĹŁPZŽ–',
		'.73' => 'AÁÄCČTŤVYÝGw',
		'.77' => 'DĎOÓÖÔRŘŔUÚŮÜKXQ',
		'.81' => 'HNŇm',
		'.92' => 'M',
		'1.0' => 'W',
	];

	/** The same of the SemiBold italic, in which uncertain and unknown ancestors are written. */
	private const GaramondItalic = [
		'.23' => ' \'',
		'.29' => 'ĺ.,:;',
		'.36' => 'iíjlľłsštJ()-cč',
		'.39' => 'fťIÍ?eéě',
		'.47' => 'rřŕzžgoóöôvyý',
		'.5' => 'xp',
		'.53' => 'aáäSŠbkq0123456789~F',
		'.56' => 'dh≈',
		'.62' => 'ďnňuúůüBEÉĚLĽĹŁP–',
		'.67' => 'ZŽCČTŤYÝ',
		'.73' => 'AÁÄVGwOÓÖÔUÚŮÜXQ',
		'.77' => 'DĎRŘŔKH',
		'.81' => 'NŇ',
		'.86' => 'm',
		'1.0' => 'M',
		'1.1' => 'W',
	];

	private const Default = .8;

	/** A little room for the kerning and the rounding. */
	private const Spare = 1.02;

	public function of(string $text, PosterStyle $style, bool $italic = false): float
	{
		if (!$style->usesWebFont()) {
			return FanGeometry::GlyphWidth * mb_strlen($text);
		}

		$width = 0.0;
		foreach (mb_str_split($text) as $letter) {
			$width += self::letter($letter, $italic ? self::GaramondItalic : self::Garamond);
		}

		return $width * self::Spare;
	}

	/** @param array<string, string> $table */
	private static function letter(string $letter, array $table): float
	{
		foreach ($table as $width => $letters) {
			if (str_contains($letters, $letter)) {
				return (float) $width;
			}
		}

		return self::Default;
	}
}
