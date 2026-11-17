<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Where the painted tree may put its leaves: inside its area, off the name plaques and off the corner kept for
 * the legend. Only the plaques around the point are looked at (those of the rows around it that reach to its x),
 * so the check does not slow down with the tree.
 */
final readonly class PaintedSpace
{
	/** Plaques are kept clear with this much room around. */
	private const Clearance = 1.1;

	/** @var array<int, list<PaintedNode>> generation → its ancestors from the left */
	private array $rows;

	/** @var array<int, float> generation → how far to the side of its centre its widest plaque is kept clear */
	private array $reach;

	/** @param array<int, array<int, PaintedNode>> $nodes generation → index → ancestor */
	public function __construct(
		array $nodes,
		private float $width,
		private float $height,
		/** Height of a generation's row and the y of the boundary between the person's row and the parents'. */
		private float $unit,
		private float $top,
		/** Size of the corner kept free, bottom right. */
		private float $cornerWidth,
		private float $cornerHeight,
	) {
		$rows = [];
		$reach = [];
		foreach ($nodes as $g => $row) {
			$row = array_values($row);
			usort($row, static fn (PaintedNode $a, PaintedNode $b): int => $a->x <=> $b->x);
			$rows[$g] = $row;
			$reach[$g] = $row === []
				? 0.0
				: max(array_map(static fn (PaintedNode $node): float => $node->w, $row)) / 2 * self::Clearance;
		}
		$this->rows = $rows;
		$this->reach = $reach;
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
			if ($this->covered($g, $x, $y)) {
				return false;
			}
		}

		return true;
	}

	/** Whether the point lies on a plaque of the generation or too close to one. */
	private function covered(int $g, float $x, float $y): bool
	{
		$nodes = $this->rows[$g] ?? [];
		$reach = $this->reach[$g] ?? 0.0;
		// The first plaque that may reach to the point; those left of it are too far.
		$k = 0;
		$end = count($nodes);
		while ($k < $end) {
			$middle = intdiv($k + $end, 2);
			if ($nodes[$middle]->x < $x - $reach) {
				$k = $middle + 1;
			} else {
				$end = $middle;
			}
		}
		for ($count = count($nodes); $k < $count && $nodes[$k]->x <= $x + $reach; $k++) {
			if ($nodes[$k]->covers($x, $y, self::Clearance)) {
				return true;
			}
		}

		return false;
	}
}
