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

	public function __construct(
		private Repository $repository,
		private View $view,
		private PosterFan $fan,
		private PosterTree $tree,
		private PosterDecor $decor,
		private PosterFonts $fonts,
		private FanGeometry $geometry,
	)
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

	/** @param bool $standalone for a file that leaves the website: with the font files inside */
	public function render(Person $root, PosterOptions $options, bool $standalone = false): PosterSheet
	{
		$width = $options->width();
		$height = $options->height();
		$margin = min($width, $height) * self::Margin;
		$area = [$margin, $width - 2 * $margin, $height - 2 * $margin];
		$generations = $this->repository->ancestorGenerations($root->slug, $options->depth);
		$defs = [];

		[$chart, $title, $footer] = $options->layout->isFan()
			? $this->fanSheet($root, $options, $generations, $area, $defs)
			: $this->treeSheet($root, $options, $generations, $area);

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" class="poster-svg" viewBox="0 0 %d %d" width="%dmm" height="%dmm"'
			. ' font-family="%s" role="img" aria-label="%s">',
			$width,
			$height,
			$width,
			$height,
			$this->view->e($options->style->font()),
			$this->view->e('Předci osoby ' . $root->name),
		)
			. '<style>' . ($options->style->usesWebFont() ? $this->fonts->css($standalone) : '') . $this->css($options->style) . '</style>'
			. '<defs>' . implode('', $defs) . '</defs>'
			. $this->decor->paper($options->style, $width, $height)
			. $this->decor->frame($options->style, $width, $height, $margin)
			. $chart . $title . $footer
			. '</svg>';

		// Only the names count: the title follows its length and the legend its corner, not the generations.
		return new PosterSheet($svg, $options->depth, $this->smallestFont($chart));
	}

	/**
	 * The fan in the middle of the sheet (the half fan standing on the bottom edge), the title in the top left
	 * corner, the legend in the bottom right corner (top right for the half fan).
	 *
	 * @param non-empty-list<list<?string>> $generations
	 * @param array{float, float, float} $area margin, width and height of the area inside the margins
	 * @param list<string> $defs
	 * @return array{string, string, string} chart, title, legend
	 */
	private function fanSheet(Person $root, PosterOptions $options, array $generations, array $area, array &$defs): array
	{
		[$margin, $areaWidth, $areaHeight] = $area;
		$half = $options->layout === PosterLayout::HalfFan;
		if ($half) {
			// The upper half of a circle standing on its diameter at the bottom edge; the title goes above it.
			$radius = min($areaWidth / 2, $areaHeight);
			$cy = $margin + $areaHeight;
		} else {
			$radius = min($areaWidth, $areaHeight) / 2;
			$cy = $margin + $areaHeight / 2;
		}

		$chart = sprintf(
			'<g transform="translate(%.1f,%.1f)">%s</g>',
			$margin + $areaWidth / 2,
			$cy,
			$this->fan->render($generations, $options->style, $radius, $half, $defs),
		);

		$rows = $this->footerRows($generations, $options->style);
		$footerSize = max(self::MinFooterFont, $radius * .022);
		$footer = $this->footer($rows, $footerSize, $margin + $areaWidth, $half ? $margin : $margin + $areaHeight, $half);

		// Free room for the title: a band above the chart (beside the legend of the half fan), or the corner beside it.
		$size = $radius * .075;
		$band = $cy - $radius - $margin;
		$reserved = $half ? $this->footerWidth($rows, $footerSize) + 2 * $footerSize : 0.0;
		$available = $band >= $this->titleHeight($size)
			? $areaWidth - $reserved
			: ($areaWidth - 2 * $radius) / 2 + $radius * .5;

		return [$chart, $this->title($root, $options, $margin, $size, $available), $footer];
	}

	/**
	 * The title in a band across the top, the tree below it, the legend in the free corner beside the person.
	 *
	 * @param non-empty-list<list<?string>> $generations
	 * @param array{float, float, float} $area margin, width and height of the area inside the margins
	 * @return array{string, string, string} chart, title, legend
	 */
	private function treeSheet(Person $root, PosterOptions $options, array $generations, array $area): array
	{
		[$margin, $areaWidth, $areaHeight] = $area;
		$upward = $options->layout === PosterLayout::Tree;
		$size = min($areaWidth, $areaHeight) * .0375;
		$band = $this->titleHeight($size) + $size * .8;
		$treeHeight = $areaHeight - $band;

		$chart = sprintf(
			'<g transform="translate(%.1f,%.1f)">%s</g>',
			$margin,
			$margin + $band,
			$this->tree->render($generations, $options->style, $areaWidth, $treeHeight, $upward, $size * .9),
		);

		$rows = $this->footerRows($generations, $options->style);
		[$cornerWidth, $cornerHeight] = $this->tree->corner(count($generations) - 1, $areaWidth, $treeHeight, $upward);
		$footerSize = min(
			max(self::MinFooterFont, min($areaWidth, $areaHeight) * .011),
			$cornerWidth / $this->footerWidth($rows, 1.0),
			$cornerHeight * .9 / (count($rows) * 1.6),
		);
		$right = $upward ? $margin + $areaWidth : $margin + $cornerWidth;
		$footer = $this->footer($rows, $footerSize, $right, $margin + $areaHeight, false);

		return [$chart, $this->title($root, $options, $margin, $size, $areaWidth), $footer];
	}

	/** Height of the heading and the subtitle written with the given heading size. */
	private function titleHeight(float $size): float
	{
		return $size * 1.2 + $size * .45 * 1.6;
	}

	/** Heading (the visitor's or the site's title) and the subtitle in the top left corner, fitted to $available. */
	private function title(Person $root, PosterOptions $options, float $margin, float $size, float $available): string
	{
		$heading = $options->title !== '' ? $options->title : $this->repository->title();
		$subtitle = 'Předci osoby ' . $root->name . ' · ' . $this->view->plural($options->depth, 'generace', 'generace', 'generací');
		$subSize = $this->geometry->fit($subtitle, $available, $size * .45);
		$size = $this->geometry->fit($heading, $available, $size);
		// The glyph width is an average; wide capitals get a little room to spare.
		$textWidth = max(mb_strlen($heading) * $size, mb_strlen($subtitle) * $subSize) * FanGeometry::GlyphWidth * 1.1;
		$cartouche = $this->decor->cartouche(
			$options->style,
			$margin,
			$margin,
			min($available, $textWidth),
			$size * 1.2 + $subSize * 1.4,
			min($size * .4, $margin * .3),
		);

		return $cartouche . sprintf(
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
	 * @param non-empty-list<list<?string>> $generations the drawn ancestors
	 * @return non-empty-list<array{string, string, ?string}> [text, CSS class, colour]
	 */
	private function footerRows(array $generations, PosterStyle $style): array
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
			if (isset($drawn[$line->k])) {
				$rows[] = ['rod ' . $line->name, 'pst-t', $style->tint($line->color)];
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
			. '.pst-root{fill:' . $style->accentInk() . '}'
			. '.pst-years{font-weight:400}';
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
