<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Where the painted tree may put its leaves: inside its area, off the name plaques and off the corner kept for
 * the legend. The plaques are looked up by their row and place, so the check does not slow down with the tree.
 */
final readonly class PaintedSpace
{
	/** Plaques are kept clear with this much room around. */
	private const Clearance = 1.1;

	public function __construct(
		/** @var array<int, array<int, PaintedNode>> generation → index → ancestor */
		private array $nodes,
		private float $width,
		private float $height,
		/** Height of a generation's row and the y of the boundary between the person's row and the parents'. */
		private float $unit,
		private float $top,
		/** Size of the corner kept free, bottom right. */
		private float $cornerWidth,
		private float $cornerHeight,
	) {
	}

	public function free(float $x, float $y): bool
	{
		if ($x < 0 || $x > $this->width || $y < 0 || $y > $this->height) {
			return false;
		}
		if ($x > $this->width - $this->cornerWidth && $y > $this->height - $this->cornerHeight) {
			return false;
		}

		$row = (int) round(($this->top - $y) / $this->unit + .5);
		for ($g = max(0, $row - 1); $g <= $row + 1; $g++) {
			$place = (int) floor($x / ($this->width / 2 ** $g));
			for ($i = $place - 1; $i <= $place + 1; $i++) {
				if (isset($this->nodes[$g][$i]) && $this->nodes[$g][$i]->covers($x, $y, self::Clearance)) {
					return false;
				}
			}
		}

		return true;
	}
}
