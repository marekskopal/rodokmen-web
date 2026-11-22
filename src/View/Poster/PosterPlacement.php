<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** Where the heading or the footer of the poster goes: to the left or the right side of the sheet, or nowhere. */
enum PosterPlacement: string
{
	case Off = 'vypnuto';
	case Left = 'vlevo';
	case Right = 'vpravo';

	public function label(): string
	{
		return match ($this) {
			self::Off => 'vypnuto',
			self::Left => 'vlevo',
			self::Right => 'vpravo',
		};
	}
}
