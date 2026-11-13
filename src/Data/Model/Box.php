<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\InvalidDataException;

/** Highlighted area on a record scan, as fractions of the image. */
final readonly class Box
{
	public function __construct(public float $x0, public float $y0, public float $x1, public float $y1,)
	{
	}

	/** @param list<float> $values [x0, y0, x1, y1] */
	public static function fromList(array $values): self
	{
		if (count($values) !== 4) {
			throw new InvalidDataException('Box needs 4 numbers, got ' . count($values));
		}

		return new self($values[0], $values[1], $values[2], $values[3]);
	}
}
