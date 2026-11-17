<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** Paper sizes of the ancestor poster (ISO 216). */
enum PaperFormat: string
{
	case A4 = 'a4';
	case A3 = 'a3';
	case A2 = 'a2';
	case A1 = 'a1';
	case A0 = 'a0';

	/** @return array{int, int} short and long side in millimetres */
	public function size(): array
	{
		return match ($this) {
			self::A4 => [210, 297],
			self::A3 => [297, 420],
			self::A2 => [420, 594],
			self::A1 => [594, 841],
			self::A0 => [841, 1189],
		};
	}

	public function label(): string
	{
		return strtoupper($this->value);
	}
}
