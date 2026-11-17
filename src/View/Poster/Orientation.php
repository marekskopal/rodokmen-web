<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

enum Orientation: string
{
	case Landscape = 'na-sirku';
	case Portrait = 'na-vysku';

	public function label(): string
	{
		return match ($this) {
			self::Landscape => 'na šířku',
			self::Portrait => 'na výšku',
		};
	}
}
