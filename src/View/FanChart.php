<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Rodokmen\Site\Data\Model\Line;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;

/**
 * 360° ancestor fan chart as inline SVG: the person in the centre, every ring one generation older,
 * the father's side on the left, the mother's side on the right. Ported from the skill's tools/fan_chart.py,
 * but drawn for any person and coloured through CSS classes so that it follows the light/dark theme.
 */
final readonly class FanChart
{
	public const DefaultDepth = 10;

	public const MaxDepth = 12;

	/** Width of each ring in SVG units; index 0 is the radius of the centre circle. */
	private const RingWidths = [240, 200, 210, 230, 220, 280, 260, 230, 140, 120, 110, 110, 110];

	/** Generations up to this one are written along the arcs, older ones along the radius. */
	private const LastArcGeneration = 4;

	/** Font sizes [name, years] of generations written along the arcs. */
	private const ArcFont = [1 => [40.0, 30.0], 2 => [34.0, 27.0], 3 => [30.0, 24.0], 4 => [27.0, 22.0]];

	/** Font size limits [min, max] of generations written along the radius. */
	private const RadialFont = [
		5 => [15.0, 26.0],
		6 => [13.0, 22.0],
		7 => [11.0, 18.0],
		8 => [9.5, 14.0],
		9 => [8.5, 11.0],
		10 => [7.5, 9.0],
		11 => [6.5, 8.0],
		12 => [6.0, 7.0],
	];

	/** Room left free along the radius in generations written along the radius. */
	private const RadialPad = 22.0;

	/** Segment opacity by generation (older generations are paler). */
	private const Opacity = [1 => .6, 2 => .6, 3 => 1, 4 => .82, 5 => .68, 6 => .56, 7 => .45, 8 => .36, 9 => .28, 10 => .22, 11 => .19, 12 => .16];

	/** Colours of people outside any family line: father's side, mother's side. */
	public const SideColors = ['#C9A97C', '#8FAFC9'];

	private const Padding = 20;

	/** Number of hatch periods along the chart's radius. */
	private const HatchesPerRadius = 60;

	public function __construct(
		private Repository $repository,
		private View $view,
		private FanGeometry $geometry,
		private FanLabels $labels,
		private FanYears $years,
	) {
	}

	/** Number of generations above the person that the chart can show (at most MaxDepth). */
	public function availableDepth(Person $root): int
	{
		return count($this->repository->ancestorGenerations($root->slug, self::MaxDepth)) - 1;
	}

	public function render(Person $root, int $depth): string
	{
		$generations = $this->generations($root, $depth);
		$radius = array_sum(array_slice(self::RingWidths, 0, count($generations))) + self::Padding;

		// Uncertain ancestors are hatched: their colour still tells the family line, the paleness the generation.
		// The stripes grow with the chart so that they stay a few pixels wide on screen at any depth.
		$hatch = (int) round($radius / self::HatchesPerRadius);
		$defs = [sprintf(
			'<pattern id="fan-hatch" patternUnits="userSpaceOnUse" width="%1$d" height="%1$d" patternTransform="rotate(45)">'
			. '<rect class="fan-hatch" width="%2$d" height="%1$d"/></pattern>',
			$hatch,
			intdiv($hatch, 3),
		)];
		$empty = [];
		$people = [$this->rootNode($root)];
		for ($g = 1; $g < count($generations); $g++) {
			foreach ($generations[$g] as $i => $slug) {
				// Draw a slot only where the child is known: an empty slot means "parent not found yet".
				if ($generations[$g - 1][intdiv($i, 2)] === null) {
					continue;
				}

				$person = $this->repository->person($slug);
				if ($person === null) {
					$empty[] = sprintf('<path class="fan-empty" d="%s" stroke-width="%s"/>', $this->sector($g, $i), $this->stroke($g));
					continue;
				}

				$people[] = $this->personNode($person, $g, $i, $defs);
			}
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" class="fan" viewBox="%d %d %d %d"',
			-$radius,
			-$radius,
			2 * $radius,
			2 * $radius,
		)
			. ' role="img" aria-label="Vějíř předků – ' . $this->view->e($root->name) . '">'
			. '<defs>' . implode('', $defs) . '</defs>'
			. implode('', $empty) . implode('', $people) . '</svg>';
	}

	/** The keys of the chart that the person's fan of $depth generations draws. */
	public function legend(Person $root, int $depth): FanLegend
	{
		$generations = $this->generations($root, $depth);
		$drawn = [];
		$sides = false;
		$uncertain = false;
		$unknown = false;
		for ($g = 1; $g < count($generations); $g++) {
			foreach ($generations[$g] as $i => $slug) {
				if ($generations[$g - 1][intdiv($i, 2)] === null) {
					continue;
				}

				$person = $this->repository->person($slug);
				if ($person === null || $person->st === 'unk') {
					$unknown = true;
					continue;
				}

				$uncertain = $uncertain || $this->uncertain($person);
				if ($person->line === null) {
					$sides = true;
				} else {
					$drawn[$person->line] = true;
				}
			}
		}

		$lines = array_values(array_filter($this->repository->lines(), static fn (Line $line): bool => isset($drawn[$line->k])));

		return new FanLegend($lines, $sides, $uncertain, $unknown);
	}

	public function shortYears(string $y): string
	{
		return $this->years->format($y);
	}

	private function rootNode(Person $root): string
	{
		$r = self::RingWidths[0];
		$years = $this->shortYears($root->y);
		$size = $this->geometry->fit($root->name, 2 * $r * .85, 44);

		return $this->link($root, $years)
			. sprintf('<circle class="fan-root" r="%d"/>', $r)
			. $this->labels->text('fan-t fan-t-root fan-t-name', -8, $size, $root->name)
			. ($years !== '' ? $this->labels->text('fan-t fan-t-root', 46, 34, $years) : '')
			. '</a>';
	}

	/** @param list<string> $defs */
	private function personNode(Person $person, int $g, int $i, array &$defs): string
	{
		[$r0, $r1] = $this->radii($g);
		[$a0, $a1] = $this->angles($g, $i);
		$years = $this->shortYears($person->y);
		$style = match ($person->st) {
			'unk' => ' fan-t-unk',
			'prob', 'mh' => ' fan-t-prob',
			default => '',
		};

		$text = $g <= self::LastArcGeneration
			? $this->labels->alongArc(sprintf('fp%d_%d', $g, $i), $this->arcLines($person, $g, $years, $style), $r0, $r1, $a0, $a1, $defs)
			: $this->labels->alongRadius(
				$person->name,
				$years,
				['fan-t fan-t-name' . $style, 'fan-t' . $style],
				$r0,
				$r1,
				$a0,
				$a1,
				self::RadialFont[$g],
				self::RadialPad,
			);

		return $this->link($person, $years) . $this->segment($person, $g, $i) . $text . '</a>';
	}

	/** @return non-empty-list<array{string, float, string}> */
	private function arcLines(Person $person, int $g, string $years, string $style): array
	{
		$lines = [];
		$line = $this->repository->lineByNumber($person->line);
		if ($line !== null && $line->root === $person->slug) {
			$lines[] = [mb_strtoupper($line->name), 21.0, 'fan-t fan-t-line'];
		}
		$lines[] = [$person->name, self::ArcFont[$g][0], 'fan-t fan-t-name' . $style];
		if ($years !== '') {
			$lines[] = [$years, self::ArcFont[$g][1], 'fan-t' . $style];
		}

		return $lines;
	}

	private function segment(Person $person, int $g, int $i): string
	{
		if ($person->st === 'unk') {
			return sprintf('<path class="fan-seg-unk" d="%s" stroke-width="%s"/>', $this->sector($g, $i), $this->stroke($g));
		}

		$color = $person->line !== null
			? $this->repository->lineColor($person->line)
			: self::SideColors[($i >> $g - 1) & 1];
		$sector = $this->sector($g, $i);

		return sprintf(
			'<path class="fan-seg" d="%s" stroke-width="%s" style="--c:%s;--o:%.2f"/>',
			$sector,
			$this->stroke($g),
			$this->view->e($color),
			self::Opacity[$g],
		)
			. ($this->uncertain($person) ? sprintf('<path class="fan-seg-weak" d="%s" fill="url(#fan-hatch)"/>', $sector) : '');
	}

	/** Probable ancestors and ancestors only from someone else's tree. */
	private function uncertain(Person $person): bool
	{
		return $person->st === 'prob' || $person->st === 'mh';
	}

	/** @return non-empty-list<list<?string>> */
	private function generations(Person $root, int $depth): array
	{
		return $this->repository->ancestorGenerations($root->slug, max(1, min(self::MaxDepth, $depth)));
	}

	private function link(Person $person, string $years): string
	{
		return '<a class="fan-p" href="' . $this->view->e($this->view->personUrl($person->slug)) . '"'
			. ' data-name="' . $this->view->e($person->name) . '">'
			. '<title>' . $this->view->e(trim($person->name . ' ' . $years)) . '</title>';
	}

	private function sector(int $g, int $i): string
	{
		[$r0, $r1] = $this->radii($g);
		[$a0, $a1] = $this->angles($g, $i);

		return $this->geometry->sector($r0, $r1, $a0, $a1);
	}

	/** @return array{float, float} inner and outer radius of the generation's ring */
	private function radii(int $g): array
	{
		$r0 = (float) array_sum(array_slice(self::RingWidths, 0, $g));

		return [$r0, $r0 + self::RingWidths[$g]];
	}

	/** @return array{float, float} start and end angle of the i-th ancestor in generation g (father's side first) */
	private function angles(int $g, int $i): array
	{
		$span = 360 / 2 ** $g;

		return [180 + $i * $span, 180 + ($i + 1) * $span];
	}

	private function stroke(int $g): string
	{
		return $g < 6 ? '3' : '1.6';
	}
}
