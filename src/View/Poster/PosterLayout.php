<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** How the ancestors are arranged on the poster. */
enum PosterLayout: string
{
	case Fan = 'vejir';
	case HalfFan = 'pulvejir';

	public function label(): string
	{
		return match ($this) {
			self::Fan => 'Vějíř 360°',
			self::HalfFan => 'Půlvějíř 180°',
		};
	}
}
