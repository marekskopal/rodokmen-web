<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\View;
use const INF;

/**
 * Large-format poster of a person's ancestors as one SVG sheet measured in millimetres: the title in the top left
 * corner, the chart in the middle, the family lines and the date in a free corner.
 */
final readonly class Poster
{
	public const MaxDepth = 12;

	/** Smallest letters (pt) of a poster that is still comfortably readable. */
	public const ReadablePoints = 6.0;

	/** Smallest letters of the legend and the footer, in millimetres. */
	private const MinFooterFont = 2.2;

	/** Margin around the drawing relative to the short side of the paper. */
	private const Margin = .035;

	public function __construct(private Repository $repository, private View $view, private PosterFan $fan, private FanGeometry $geometry)
	{
	}

	/** Number of generations above the person that the poster can show (at most MaxDepth). */
	public function availableDepth(Person $root): int
	{
		return count($this->repository->ancestorGenerations($root->slug, self::MaxDepth)) - 1;
	}

	/**
	 * The poster with the most generations (at most $options->depth) whose smallest letters are still readable on
	 * the chosen paper; at least one generation when there is any.
	 */
	public function readable(Person $root, PosterOptions $options): PosterSheet
	{
		for ($depth = $options->depth; $depth > 1; $depth--) {
			$sheet = $this->render($root, $options->withDepth($depth));
			if ($sheet->smallestPoints() >= self::ReadablePoints) {
				return $sheet;
			}
		}

		return $this->render($root, $options->withDepth(min(1, $options->depth)));
	}

	public function render(Person $root, PosterOptions $options): PosterSheet
	{
		$width = $options->width();
		$height = $options->height();
		$margin = min($width, $height) * self::Margin;
		$areaWidth = $width - 2 * $margin;
		$areaHeight = $height - 2 * $margin;
		$half = $options->layout === PosterLayout::HalfFan;
		$style = $options->style;

		if ($half) {
			// The upper half of a circle standing on its diameter at the bottom edge; the title goes above it.
			$radius = min($areaWidth / 2, $areaHeight);
			$cy = $margin + $areaHeight;
		} else {
			$radius = min($areaWidth, $areaHeight) / 2;
			$cy = $height / 2;
		}
		$cx = $width / 2;

		$generations = $this->repository->ancestorGenerations($root->slug, $options->depth);
		$defs = [];
		$lines = [];
		$chart = $this->fan->render($generations, $style, $radius, $half, $defs, $lines);

		$rows = $this->footerRows($lines);
		$footerSize = max(self::MinFooterFont, $radius * .022);
		$footer = $this->footer($rows, $footerSize, $margin + $areaWidth, $half ? $margin : $margin + $areaHeight, $half);

		// Free room for the title: a band above the chart (beside the legend of the half fan), or the corner beside it.
		$side = ($areaWidth - 2 * $radius) / 2;
		$reserved = $half ? $this->footerWidth($rows, $footerSize) + 2 * $footerSize : 0.0;
		$title = $this->title($root, $options, $radius, $margin, $cy - $radius - $margin, $side, $reserved);

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" class="poster-svg" viewBox="0 0 %d %d" width="%dmm" height="%dmm"'
			. ' font-family="%s" role="img" aria-label="%s">',
			$width,
			$height,
			$width,
			$height,
			$this->view->e($style->font()),
			$this->view->e('Předci osoby ' . $root->name),
		)
			. '<style>' . $this->css($style) . '</style>'
			. '<defs>' . implode('', $defs) . '</defs>'
			. sprintf('<rect width="%d" height="%d" fill="%s"/>', $width, $height, $style->paper())
			. sprintf('<g transform="translate(%.1f,%.1f)">%s</g>', $cx, $cy, $chart)
			. $title . $footer
			. '</svg>';

		// The title is left out: its size follows its length, not the number of generations.
		return new PosterSheet($svg, $options->depth, $this->smallestFont($chart . $footer));
	}

	/** @param float $reserved width kept free for the legend at the right end of the band above the chart */
	private function title(
		Person $root,
		PosterOptions $options,
		float $radius,
		float $margin,
		float $band,
		float $side,
		float $reserved,
	): string
	{
		$heading = $options->title !== '' ? $options->title : $this->repository->title();
		$subtitle = 'Předci osoby ' . $root->name . ' · ' . $this->view->plural($options->depth, 'generace', 'generace', 'generací');

		$size = $radius * .075;
		$block = $size * 1.2 + $size * .45 * 1.6;
		// Above the chart when the band is high enough, otherwise beside the curve of the chart.
		$available = $band >= $block ? $options->width() - 2 * $margin - $reserved : $side + $radius * .5;
		$size = $this->geometry->fit($heading, $available, $size);
		$subSize = $this->geometry->fit($subtitle, $available, $radius * .075 * .45);

		return sprintf(
			'<text class="pst-t pst-title" x="%.1f" y="%.1f" font-size="%.1f">%s</text>',
			$margin,
			$margin + $size * .9,
			$size,
			$this->view->e($heading),
		)
			. sprintf(
				'<text class="pst-t pst-sub" x="%.1f" y="%.1f" font-size="%.1f">%s</text>',
				$margin,
				$margin + $size * 1.2 + $subSize * 1.1,
				$subSize,
				$this->view->e($subtitle),
			);
	}

	/**
	 * Family lines with their colours, a note on uncertain ancestors, the website and the date.
	 *
	 * @param array<int, true> $drawn numbers of the family lines in the chart
	 * @return non-empty-list<array{string, string, ?string}> [text, CSS class, colour]
	 */
	private function footerRows(array $drawn): array
	{
		$rows = [];
		foreach ($this->repository->lines() as $line) {
			if (isset($drawn[$line->k])) {
				$rows[] = ['rod ' . $line->name, 'pst-t', $line->color];
			}
		}
		$rows[] = ['kurzívou pravděpodobní nebo nejistí předci', 'pst-t pst-prob pst-muted', null];
		$rows[] = [$this->repository->title() . ' · ' . date('j. n. Y'), 'pst-t pst-muted', null];

		return $rows;
	}

	/**
	 * Estimated width of the widest footer row, colour swatch included.
	 *
	 * @param non-empty-list<array{string, string, ?string}> $rows
	 */
	private function footerWidth(array $rows, float $size): float
	{
		$width = 0.0;
		foreach ($rows as [$text, , $color]) {
			$width = max($width, mb_strlen($text) * FanGeometry::GlyphWidth * $size + ($color !== null ? $size * 1.5 : 0.0));
		}

		return $width;
	}

	/**
	 * Footer rows right-aligned in the top or bottom corner.
	 *
	 * @param non-empty-list<array{string, string, ?string}> $rows
	 */
	private function footer(array $rows, float $size, float $right, float $edge, bool $top): string
	{
		$step = $size * 1.6;
		$y = $top ? $edge + $size : $edge - ($step * (count($rows) - 1));
		$out = '';
		foreach ($rows as [$text, $class, $color]) {
			$x = $right;
			if ($color !== null) {
				$out .= sprintf(
					'<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f" fill="%s" fill-opacity=".66"/>',
					$right - $size,
					$y - $size * .8,
					$size,
					$size,
					$size * .2,
					$this->view->e($color),
				);
				$x -= $size * 1.5;
			}
			$out .= sprintf(
				'<text class="%s" x="%.1f" y="%.1f" font-size="%.1f" text-anchor="end">%s</text>',
				$class,
				$x,
				$y,
				$size,
				$this->view->e($text),
			);
			$y += $step;
		}

		return $out;
	}

	private function css(PosterStyle $style): string
	{
		return '.pst-t{fill:' . $style->ink() . '}'
			. '.pst-name,.pst-sub{font-weight:600}'
			. '.pst-title{font-weight:700}'
			. '.pst-prob{font-style:italic}'
			. '.pst-unk{fill:' . $style->muted() . ';font-style:italic}'
			. '.pst-muted{fill:' . $style->muted() . '}'
			. '.pst-line{font-weight:700;letter-spacing:.15em}'
			. '.pst-root{fill:' . $style->accentInk() . '}';
	}

	/** Smallest font size (mm) written into the SVG – tells the visitor whether the poster is still readable. */
	private function smallestFont(string $svg): float
	{
		preg_match_all('/font-size="([0-9.]+)"/', $svg, $matches);
		$smallest = INF;
		foreach ($matches[1] as $size) {
			$smallest = min($smallest, (float) $size);
		}

		return $smallest === INF ? 0.0 : $smallest;
	}
}
