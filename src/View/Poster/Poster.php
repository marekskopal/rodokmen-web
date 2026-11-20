<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use DateTimeImmutable;
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

	/** Wider margin of the historical style, which holds the illuminated border (PosterBorder). */
	private const OrnateMargin = .05;

	public function __construct(
		private Repository $repository,
		private View $view,
		private PosterFan $fan,
		private PosterTree $tree,
		private PosterPaintedTree $paintedTree,
		private PosterDecor $decor,
		private PosterBorder $border,
		private PosterFonts $fonts,
		private PosterHeading $heading,
		private PosterTitle $title,
		private PosterTreeBand $band,
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
		// The paper and the border do not depend on the depth: drawn once for all the tries.
		$backdrop = $this->backdrop($root, $options);
		for ($depth = $options->depth; $depth > 1; $depth--) {
			$sheet = $this->sheet($root, $options->withDepth($depth), false, $backdrop);
			if ($sheet->smallestPoints() >= self::ReadablePoints) {
				return $sheet;
			}
		}

		return $this->sheet($root, $options->withDepth(min(1, $options->depth)), false, $backdrop);
	}

	/** @param bool $standalone for a file that leaves the website: with the font files inside */
	public function render(Person $root, PosterOptions $options, bool $standalone = false): PosterSheet
	{
		return $this->sheet($root, $options, $standalone, $this->backdrop($root, $options));
	}

	/** The poster drawn on the given paper with its border (backdrop()). */
	private function sheet(Person $root, PosterOptions $options, bool $standalone, string $backdrop): PosterSheet
	{
		$width = $options->width();
		$height = $options->height();
		$margin = $this->margin($options);
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
			. $backdrop
			. $chart . $title . $footer
			. '</svg>';

		// Only the names count: the title follows its length and the legend its corner, not the generations.
		return new PosterSheet($svg, $options->depth, $this->smallestFont($chart));
	}

	private function margin(PosterOptions $options): float
	{
		return min($options->width(), $options->height()) * ($options->style->isOrnate() ? self::OrnateMargin : self::Margin);
	}

	/** The paper and the border in its margin: the same for every depth. */
	private function backdrop(Person $root, PosterOptions $options): string
	{
		return $this->decor->paper($options->style, $options->width(), $options->height(), new PosterRandom('paper|' . $root->slug))
			. $this->border->draw(
				$options->style,
				$options->width(),
				$options->height(),
				$this->margin($options),
				new PosterRandom('border|' . $root->slug),
			);
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

		$rows = $this->footerRows($generations, $options->style, $this->heading($root, $options));
		$footerSize = max(self::MinFooterFont, $radius * .022);
		$footer = $this->footer($rows, $footerSize, $margin + $areaWidth, $half ? $margin : $margin + $areaHeight, $half);

		// Free room for the title: a band above the chart (beside the legend of the half fan), or the corner beside it.
		$size = $radius * .075;
		$band = $cy - $radius - $margin;
		$reserved = $half ? $this->footerWidth($rows, $footerSize) + 2 * $footerSize : 0.0;
		$available = $band >= $this->title->height($size)
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
		$upward = $options->layout !== PosterLayout::TreeRight;
		$painted = $options->layout === PosterLayout::PaintedTree;
		$size = min($areaWidth, $areaHeight) * .0375;
		$band = $this->band->of(
			$options->layout,
			$generations,
			[$areaWidth, $areaHeight],
			$this->title->extent(
				$options->style,
				$this->heading($root, $options),
				$this->subtitle($root, $options),
				$size,
				$areaWidth,
				$this->titlePad($size, $margin),
			),
			$size,
		);
		$treeHeight = $areaHeight - $band;

		$chart = sprintf(
			'<g transform="translate(%.1f,%.1f)">%s</g>',
			$margin,
			$margin + $band,
			$painted
				? $this->paintedTree->render($generations, $options->style, $areaWidth, $treeHeight, $size * .9)
				: $this->tree->render($generations, $options->style, $areaWidth, $treeHeight, $upward, $size * .9),
		);

		// The painted tree colours men and women, not the family lines: it lists no lines.
		$rows = $this->footerRows($generations, $options->style, $this->heading($root, $options), !$painted);
		[$cornerWidth, $cornerHeight] = $painted
			? $this->paintedTree->corner(count($generations) - 1, $areaWidth, $treeHeight)
			: $this->tree->corner(count($generations) - 1, $areaWidth, $treeHeight, $upward);
		$footerSize = min(
			max(self::MinFooterFont, min($areaWidth, $areaHeight) * .011),
			$cornerWidth / $this->footerWidth($rows, 1.0),
			$cornerHeight * .9 / (count($rows) * 1.6),
		);
		$right = $upward ? $margin + $areaWidth : $margin + $cornerWidth;
		$footer = $this->footer($rows, $footerSize, $right, $margin + $areaHeight, false);

		return [$chart, $this->title($root, $options, $margin, $size, $areaWidth), $footer];
	}

	/** Heading (the visitor's, or the family of the person) and the subtitle in the top left corner, fitted to $available. */
	private function title(Person $root, PosterOptions $options, float $margin, float $size, float $available): string
	{
		return $this->title->draw(
			$options->style,
			$this->heading($root, $options),
			$this->subtitle($root, $options),
			$margin,
			$margin,
			$size,
			$available,
			$this->titlePad($size, $margin),
			new PosterRandom('title|' . $root->slug),
		);
	}

	private function heading(Person $root, PosterOptions $options): string
	{
		return $options->title !== '' ? $options->title : $this->heading->of($root);
	}

	private function subtitle(Person $root, PosterOptions $options): string
	{
		return 'Předci osoby ' . $root->name . ' · ' . $this->view->plural($options->depth, 'generace', 'generace', 'generací');
	}

	/** The scroll's rolls reach 1.3 × the padding into the margin, clear of the corner medallion (PosterBorder::Corner). */
	private function titlePad(float $size, float $margin): float
	{
		return min($size * .4, $margin * .22);
	}

	/**
	 * Family lines with their colours, a note on uncertain ancestors, the heading of the poster and the date.
	 *
	 * @param non-empty-list<list<?string>> $generations the drawn ancestors
	 * @param string $heading the poster's heading, repeated beside the date
	 * @param bool $lines whether to list the family lines
	 * @return non-empty-list<array{string, string, ?string}> [text, CSS class, colour]
	 */
	private function footerRows(array $generations, PosterStyle $style, string $heading, bool $lines = true): array
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
		$rows[] = [$heading . ' · ' . $style->date(new DateTimeImmutable()), 'pst-t pst-muted', null];

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
			. '.pst-years{font-weight:400}'
			. ($style->isOrnate()
				? '.pst-rubric{fill:' . PosterPigments::Vermilion . '}'
					. '.pst-initial{fill:' . PosterPigments::Gold . ';stroke:' . PosterPigments::GoldShade . ';font-weight:700}'
				: '');
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
