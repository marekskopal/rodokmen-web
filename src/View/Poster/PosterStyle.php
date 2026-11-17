<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Look of the poster: paper, ink and typeface. The colours are written into the SVG itself, so the poster looks
 * the same on screen (also in dark mode), on paper and outside the website.
 */
enum PosterStyle: string
{
	case Simple = 'jednoduchy';

	public function label(): string
	{
		return match ($this) {
			self::Simple => 'Jednoduchý',
		};
	}

	public function paper(): string
	{
		return '#ffffff';
	}

	public function ink(): string
	{
		return '#2a2622';
	}

	public function muted(): string
	{
		return '#8a8174';
	}

	/** Fill of places whose ancestor is not known yet. */
	public function empty(): string
	{
		return '#e2d9c8';
	}

	/** Circle of the person in the centre. */
	public function accent(): string
	{
		return '#7a3e2b';
	}

	public function accentInk(): string
	{
		return '#ffffff';
	}

	public function font(): string
	{
		return '"Iowan Old Style", "Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif';
	}
}
