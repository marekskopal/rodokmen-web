<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** A trunk, branch, twig or root of the painted tree: points of its middle line and its width at each of them. */
final readonly class PaintedLimb
{
	public function __construct(
		/** @var non-empty-list<array{float, float}> */
		public array $spine,
		/** @var non-empty-list<float> */
		public array $widths,
	) {
	}

	public function thickness(): float
	{
		return max($this->widths);
	}

	/** @return array{float, float} point at the share $t of the limb (0 = start, 1 = end) */
	public function at(float $t): array
	{
		return $this->spine[$this->index($t)];
	}

	public function widthAt(float $t): float
	{
		return $this->widths[$this->index($t)] ?? $this->widths[0];
	}

	/** Direction of the limb at the share $t, in degrees (0 = to the right, clockwise). */
	public function angleAt(float $t): float
	{
		$i = $this->index($t);
		[$ax, $ay] = $this->spine[max(0, $i - 1)];
		[$bx, $by] = $this->spine[min(count($this->spine) - 1, $i + 1)];

		return rad2deg(atan2($by - $ay, $bx - $ax));
	}

	/** @return int<0, max> */
	private function index(float $t): int
	{
		return max(0, min(count($this->spine) - 1, (int) round($t * (count($this->spine) - 1))));
	}
}
