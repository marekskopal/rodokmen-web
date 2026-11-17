<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Repeatable "chance" of the painted poster: the same seed gives the same numbers on every request and every
 * machine, so the same tree is drawn every time. fork() gives every part of the drawing its own sequence, so that
 * changing one part (one more leaf) does not reshuffle the others.
 */
final readonly class PosterRandom
{
	private Randomizer $randomizer;

	public function __construct(private string $seed)
	{
		$this->randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed, true)));
	}

	public function fork(string $key): self
	{
		return new self($this->seed . '|' . $key);
	}

	public function float(float $min, float $max): float
	{
		return $min < $max ? $this->randomizer->getFloat($min, $max) : $min;
	}

	public function int(int $min, int $max): int
	{
		return $min < $max ? $this->randomizer->getInt($min, $max) : $min;
	}

	public function chance(float $probability): bool
	{
		return $this->float(0, 1) < $probability;
	}

	/** -1 or 1. */
	public function side(): int
	{
		return $this->chance(.5) ? 1 : -1;
	}

	/**
	 * @param non-empty-list<T> $items
	 * @return T
	 * @template T
	 */
	public function pick(array $items): mixed
	{
		return $items[$this->int(0, count($items) - 1)];
	}
}
