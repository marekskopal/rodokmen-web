<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use DateTimeInterface;

/**
 * Look of the poster: paper, ink, typeface and decoration. The colours are written into the SVG itself, so the
 * poster looks the same on screen (also in dark mode), on paper and outside the website.
 */
enum PosterStyle: string
{
	/** Like the website: white paper, the colours of the family lines. */
	case Simple = 'jednoduchy';

	/** Like old painted family trees: parchment, sepia ink, an ornamental frame, EB Garamond, muted colours. */
	case Historical = 'historicky';

	/** Colour the line colours are mixed with in the historical style, and how much of it. */
	private const Sepia = [139, 107, 61];

	private const SepiaShare = .3;

	private const Numerals = [
		'M' => 1000,
		'CM' => 900,
		'D' => 500,
		'CD' => 400,
		'C' => 100,
		'XC' => 90,
		'L' => 50,
		'XL' => 40,
		'X' => 10,
		'IX' => 9,
		'V' => 5,
		'IV' => 4,
		'I' => 1,
	];

	public function label(): string
	{
		return match ($this) {
			self::Simple => 'Jednoduchý',
			self::Historical => 'Historický',
		};
	}

	/** Parchment, frame, cartouche and labels with cut corners instead of plain boxes. */
	public function isOrnate(): bool
	{
		return $this === self::Historical;
	}

	/** Uses the EB Garamond web font (PosterFonts) rather than the fonts of the computer. */
	public function usesWebFont(): bool
	{
		return $this === self::Historical;
	}

	/** Plain colour of the paper; the historical paper is a gradient around it (PosterDecor). */
	public function paper(): string
	{
		return match ($this) {
			self::Simple => '#ffffff',
			self::Historical => '#f3e6c4',
		};
	}

	public function ink(): string
	{
		return match ($this) {
			self::Simple => '#2a2622',
			self::Historical => '#3b2614',
		};
	}

	public function muted(): string
	{
		return match ($this) {
			self::Simple => '#8a8174',
			self::Historical => '#8a6a45',
		};
	}

	/** Fill of places whose ancestor is not known yet. */
	public function empty(): string
	{
		return match ($this) {
			self::Simple => '#e2d9c8',
			self::Historical => '#e4d1a6',
		};
	}

	/** Circle or box of the person in the centre. */
	public function accent(): string
	{
		return match ($this) {
			self::Simple => '#7a3e2b',
			self::Historical => '#6e2417',
		};
	}

	public function accentInk(): string
	{
		return match ($this) {
			self::Simple => '#ffffff',
			self::Historical => '#f6ead0',
		};
	}

	public function font(): string
	{
		return match ($this) {
			self::Simple => '"Iowan Old Style", "Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif',
			self::Historical => '"' . PosterFonts::Family . '", Garamond, "Times New Roman", serif',
		};
	}

	/** Date on the poster: "20. 11. 2026", or in the historical style "dne 20. 11. léta Páně MMXXVI". */
	public function date(DateTimeInterface $date): string
	{
		return match ($this) {
			self::Simple => $date->format('j. n. Y'),
			self::Historical => 'dne ' . $date->format('j. n.') . ' léta Páně ' . self::roman((int) $date->format('Y')),
		};
	}

	/** Colour of a family line on the poster: as it is, or mixed with sepia in the historical style. */
	public function tint(string $color): string
	{
		// #rgb is short for #rrggbb; other notations are left as they are.
		$hex = preg_replace('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', '#$1$1$2$2$3$3', $color) ?? $color;
		if ($this !== self::Historical || preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m) !== 1) {
			return $color;
		}

		$mixed = '#';
		foreach (self::Sepia as $n => $sepia) {
			$mixed .= sprintf('%02x', (int) round(hexdec($m[$n + 1]) * (1 - self::SepiaShare) + $sepia * self::SepiaShare));
		}

		return $mixed;
	}

	/** A positive number in Roman numerals: 2026 → MMXXVI. */
	private static function roman(int $number): string
	{
		$roman = '';
		foreach (self::Numerals as $numeral => $value) {
			$roman .= str_repeat($numeral, intdiv($number, $value));
			$number %= $value;
		}

		return $roman;
	}
}
