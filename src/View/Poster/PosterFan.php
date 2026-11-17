<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\FanChart;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\FanLabels;
use Rodokmen\Site\View\FanYears;
use Rodokmen\Site\View\View;

/**
 * Ancestor fan of the poster, centred at 0,0 and measured in millimetres: a full circle (360°) or its upper half
 * (180°). Unlike the fan chart on the website, the rings and letters are sized for the given radius, so the text
 * stays as large as the paper allows.
 */
final readonly class PosterFan
{
	/** Radius of the centre circle relative to the width of a ring. */
	private const CentreWeight = 1.15;

	/** Width of the rings of generations 1 … LastArcGeneration (two or three lines along the arc) and of older ones. */
	private const ArcRingWeight = .9;

	private const RadialRingWeight = 1.0;

	/** Generations up to this one are written along the arc when their segments are wide enough. */
	private const LastArcGeneration = 4;

	public function __construct(
		private Repository $repository,
		private View $view,
		private FanGeometry $geometry,
		private FanLabels $labels,
		private FanYears $years,
	) {
	}

	/**
	 * @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first
	 * @param list<string> $defs text paths, added to the SVG's defs
	 * @param array<int, true> $lines numbers of the family lines that were drawn
	 */
	public function render(array $generations, PosterStyle $style, float $radius, bool $half, array &$defs, array &$lines): string
	{
		$edges = $this->edges(count($generations) - 1, $radius);
		$stroke = min(.5, $edges[1] / self::CentreWeight * .012);

		$out = '';
		for ($g = 1; $g < count($generations); $g++) {
			foreach ($generations[$g] as $i => $slug) {
				// Draw a place only where the child is known: an empty place means "parent not found yet".
				if ($generations[$g - 1][intdiv($i, 2)] === null) {
					continue;
				}

				[$a0, $a1] = $this->angles($g, $i, $half);
				$sector = $this->geometry->sector($edges[$g], $edges[$g + 1], $a0, $a1);
				$person = $this->repository->person($slug);
				if ($person === null) {
					$out .= $this->path($sector, $style->empty(), 1.0, $style->paper(), $stroke);
					continue;
				}

				if ($person->line !== null) {
					$lines[$person->line] = true;
				}
				$out .= $this->segment($person, $style, $sector, $g, $i, $stroke)
					. $this->label($person, $g, $i, $edges[$g], $edges[$g + 1], $a0, $a1, $defs);
			}
		}

		return $this->root($generations[0][0], $style, $edges[1], $half) . $out;
	}

	/**
	 * Radii of the ring edges: index 0 is the centre, index g + 1 the outer edge of generation g.
	 *
	 * @return non-empty-list<float>
	 */
	private function edges(int $depth, float $radius): array
	{
		$weights = [self::CentreWeight];
		for ($g = 1; $g <= $depth; $g++) {
			$weights[] = $g <= self::LastArcGeneration ? self::ArcRingWeight : self::RadialRingWeight;
		}
		$unit = $radius / array_sum($weights);
		$edges = [0.0];
		foreach ($weights as $weight) {
			$edges[] = $edges[count($edges) - 1] + $weight * $unit;
		}

		return $edges;
	}

	private function root(?string $slug, PosterStyle $style, float $r, bool $half): string
	{
		$person = $this->repository->person($slug);
		$name = $person->name ?? '';
		$years = $this->years->format($person->y ?? '');

		if ($half) {
			$shape = sprintf('<path d="M%.1f,0 A%.1f,%.1f 0 0 1 %.1f,0 Z" fill="%s"/>', -$r, $r, $r, $r, $style->accent());
			[$nameY, $yearsY, $width] = [-$r * .42, -$r * .14, 2 * $r * .8];
		} else {
			$shape = sprintf('<circle r="%.1f" fill="%s"/>', $r, $style->accent());
			[$nameY, $yearsY, $width] = [-$r * .02, $r * .3, 2 * $r * .85];
		}

		return $shape
			. $this->labels->text('pst-t pst-name pst-root', $nameY, $this->geometry->fit($name, $width, $r * .26), $name)
			. ($years !== '' ? $this->labels->text('pst-t pst-root', $yearsY, $this->geometry->fit($years, $width, $r * .18), $years) : '');
	}

	private function segment(Person $person, PosterStyle $style, string $sector, int $g, int $i, float $stroke): string
	{
		if ($person->st === 'unk') {
			return $this->path($sector, $style->empty(), 1.0, $style->paper(), $stroke);
		}

		$weak = $person->st === 'prob' || $person->st === 'mh';
		$color = $person->line !== null
			? $this->repository->lineColor($person->line)
			: FanChart::SideColors[($i >> $g - 1) & 1];

		return $this->path($sector, $color, max(.22, .66 - .04 * $g) * ($weak ? .6 : 1), $style->paper(), $stroke);
	}

	/** @param list<string> $defs */
	private function label(Person $person, int $g, int $i, float $r0, float $r1, float $a0, float $a1, array &$defs): string
	{
		$width = $r1 - $r0;
		$years = $this->years->format($person->y);
		$style = match ($person->st) {
			'unk' => ' pst-unk',
			'prob', 'mh' => ' pst-prob',
			default => '',
		};

		$arcLength = deg2rad($a1 - $a0) * ($r0 + $r1) / 2;
		if ($g > self::LastArcGeneration || $arcLength < $width * 1.2) {
			return $this->labels->alongRadius(
				$person->name,
				$years,
				['pst-t pst-name' . $style, 'pst-t' . $style],
				$r0,
				$r1,
				$a0,
				$a1,
				[0.0, $width * .14],
				$width * .08,
			);
		}

		$name = $width * .2;
		$lines = [];
		$line = $this->repository->lineByNumber($person->line);
		if ($line !== null && $line->root === $person->slug) {
			$lines[] = [mb_strtoupper($line->name), $name * .6, 'pst-t pst-line'];
		}
		$lines[] = [$person->name, $name, 'pst-t pst-name' . $style];
		if ($years !== '') {
			$lines[] = [$years, $name * .78, 'pst-t' . $style];
		}

		return $this->labels->alongArc(sprintf('pp%d_%d', $g, $i), $lines, $r0, $r1, $a0, $a1, $defs);
	}

	/** @return array{float, float} start and end angle of the i-th ancestor in generation g (father's side first) */
	private function angles(int $g, int $i, bool $half): array
	{
		// Angles stay positive: the labels decide by fmod(angle, 360) which way up the text goes.
		[$start, $sweep] = $half ? [270, 180] : [180, 360];
		$span = $sweep / 2 ** $g;

		return [$start + $i * $span, $start + ($i + 1) * $span];
	}

	private function path(string $d, string $fill, float $opacity, string $stroke, float $strokeWidth): string
	{
		return sprintf(
			'<path d="%s" fill="%s" fill-opacity="%.2f" stroke="%s" stroke-width="%.2f"/>',
			$d,
			$this->view->e($fill),
			$opacity,
			$stroke,
			$strokeWidth,
		);
	}
}
