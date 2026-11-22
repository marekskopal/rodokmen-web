<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use DateTimeImmutable;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\View;

/**
 * Footer of the poster in a free corner on the left or the right (a top one of the half fan): the legend of the family lines, the
 * heading of the poster and the date – the parts the visitor wants (PosterParts).
 */
final readonly class PosterFooter
{
	public function __construct(private Repository $repository, private View $view)
	{
	}

	/**
	 * As far as the visitor wants them: the family lines with their colours and a note on uncertain ancestors (the
	 * legend), the heading of the poster and the date.
	 *
	 * @param non-empty-list<list<?string>> $generations the drawn ancestors
	 * @param string $heading the poster's heading, repeated beside the date
	 * @param bool $lines whether to list the family lines
	 * @return list<array{string, string, ?string}> [text, CSS class, colour]
	 */
	public function rows(array $generations, PosterOptions $options, string $heading, bool $lines = true): array
	{
		$parts = $options->parts;
		$rows = $parts->legend() ? $this->legend($generations, $options->style, $lines) : [];
		$signature = array_filter([
			$parts->name() ? $heading : '',
			$parts->date() ? $options->style->date(new DateTimeImmutable()) : '',
		], static fn (string $part): bool => $part !== '');
		if ($signature !== []) {
			$rows[] = [implode(' · ', $signature), 'pst-t pst-muted', null];
		}

		return $rows;
	}

	/**
	 * The drawn family lines with their colours and a note on uncertain ancestors.
	 *
	 * @param non-empty-list<list<?string>> $generations the drawn ancestors
	 * @param bool $lines whether to list the family lines
	 * @return non-empty-list<array{string, string, ?string}> [text, CSS class, colour]
	 */
	private function legend(array $generations, PosterStyle $style, bool $lines): array
	{
		// The person is drawn in the accent colour, not in the colour of a family line.
		$drawn = [];
		foreach (array_merge(...array_slice($generations, 1)) as $slug) {
			$line = $this->repository->person($slug)?->line;
			if ($line !== null) {
				$drawn[$line] = true;
			}
		}

		$rows = [];
		foreach ($this->repository->lines() as $line) {
			if ($lines && isset($drawn[$line->k])) {
				$rows[] = ['rod ' . $line->name, 'pst-t', $style->tint($line->color)];
			}
		}
		$rows[] = ['kurzívou pravděpodobní nebo nejistí předci', 'pst-t pst-prob pst-muted', null];

		return $rows;
	}

	/**
	 * Estimated width of the widest footer row, colour swatch included.
	 *
	 * @param list<array{string, string, ?string}> $rows
	 */
	public function width(array $rows, float $size): float
	{
		$width = 0.0;
		foreach ($rows as [$text, , $color]) {
			$width = max($width, mb_strlen($text) * FanGeometry::GlyphWidth * $size + ($color !== null ? $size * 1.5 : 0.0));
		}

		return $width;
	}

	/**
	 * Footer rows in the top or bottom corner, right-aligned at $x (left-aligned with $left, the swatches first).
	 *
	 * @param list<array{string, string, ?string}> $rows
	 */
	public function draw(array $rows, float $size, float $x, float $edge, bool $top, bool $left = false): string
	{
		$step = $size * 1.6;
		$y = $top ? $edge + $size : $edge - ($step * (count($rows) - 1));
		$out = '';
		foreach ($rows as [$text, $class, $color]) {
			$textX = $x;
			if ($color !== null) {
				$out .= sprintf(
					'<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f" fill="%s" fill-opacity=".66"/>',
					$left ? $x : $x - $size,
					$y - $size * .8,
					$size,
					$size,
					$size * .2,
					$this->view->e($color),
				);
				$textX += ($left ? 1 : -1) * $size * 1.5;
			}
			$out .= sprintf(
				'<text class="%s" x="%.1f" y="%.1f" font-size="%.1f" text-anchor="%s">%s</text>',
				$class,
				$textX,
				$y,
				$size,
				$left ? 'start' : 'end',
				$this->view->e($text),
			);
			$y += $step;
		}

		return $out;
	}
}
