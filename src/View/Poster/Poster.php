<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\View;
use const INF;

/**
 * Large-format poster of a person's ancestors as one SVG sheet measured in millimetres: the title in a top corner
 * (the left one by default), the chart in the middle, the family lines and the date in a free corner.
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
		private PosterFooter $footer,
		private HomePerson $homePerson,
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

	/**
	 * The person's descendants down to the website's default person, the child first: what the poster can add
	 * below the person (PosterOptions::$descendant). Empty when the person is not an ancestor of the default person.
	 *
	 * @return list<Person>
	 */
	public function descendantChoices(Person $root): array
	{
		$path = $this->repository->pathBetween($root->slug, $this->homePerson->defaultSlug()) ?? [];
		$people = [];
		foreach (array_slice($path, 1) as $slug) {
			$person = $this->repository->person($slug);
			if ($person !== null) {
				$people[] = $person;
			}
		}

		return $people;
	}

	/**
	 * The descendants the poster draws below the person: down to the chosen one, only on the painted tree.
	 *
	 * @return list<Person>
	 */
	private function descendants(Person $root, PosterOptions $options): array
	{
		if ($options->layout !== PosterLayout::PaintedTree || $options->descendant === '') {
			return [];
		}

		$choices = $this->descendantChoices($root);
		$slugs = array_map(static fn (Person $person): string => $person->slug, $choices);
		$index = array_search($options->descendant, $slugs, true);

		return $index === false ? [] : array_slice($choices, 0, $index + 1);
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
	 * The fan in the middle of the sheet (the half fan standing on the bottom edge), the title in a top corner, the
	 * legend in a bottom corner (a top one for the half fan) – on the sides the visitor chose.
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

		$parts = $options->parts;
		$rows = $this->footer->rows($generations, $options, $this->heading($root, $options));
		$footerSize = max(self::MinFooterFont, $radius * .022);
		// Above the half fan the legend and the title share the band: side by side, or the legend below the title
		// when both go to the same side.
		$stacked = $half && $rows !== [] && $parts->heading() && $parts->footerLeft() !== $parts->headingRight();

		// Free room for the title: a band above the chart (beside the legend of the half fan), or the corner beside it.
		$size = $radius * .075;
		$band = $cy - $radius - $margin;
		$reserved = $half && $rows !== [] && !$stacked ? $this->footer->width($rows, $footerSize) + 2 * $footerSize : 0.0;
		$available = $band >= $this->title->height($size, $this->subtitle($root, $options) !== '')
			? $areaWidth - $reserved
			: ($areaWidth - 2 * $radius) / 2 + $radius * .5;

		$edge = $half ? $margin : $margin + $areaHeight;
		if ($stacked) {
			$edge += $this->titleExtent($root, $options, $margin, $size, $available)[1] + $footerSize;
		}
		$footer = $this->footer->draw(
			$rows,
			$footerSize,
			$parts->footerLeft() ? $margin : $margin + $areaWidth,
			$edge,
			$half,
			$parts->footerLeft(),
		);

		return [$chart, $this->title($root, $options, $margin, $size, $available, $areaWidth), $footer];
	}

	/**
	 * The title in a band across the top, the tree below it, the legend in a free corner beside the person.
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
		$descendants = $this->descendants($root, $options);
		$band = !$options->parts->heading() ? 0.0 : $this->band->of(
			$options->layout,
			$generations,
			[$areaWidth, $areaHeight],
			$this->titleExtent($root, $options, $margin, $size, $areaWidth),
			$size,
			$this->subtitle($root, $options) !== '',
			$options->parts->headingRight(),
			$descendants,
		);
		$treeHeight = $areaHeight - $band;

		$chart = sprintf(
			'<g transform="translate(%.1f,%.1f)">%s</g>',
			$margin,
			$margin + $band,
			$painted
				? $this->paintedTree->render(
					$generations,
					$options->style,
					$areaWidth,
					$treeHeight,
					$size * .9,
					$options->parts->footerLeft(),
					$descendants,
				)
				: $this->tree->render($generations, $options->style, $areaWidth, $treeHeight, $upward, $size * .9),
		);

		// The painted tree colours men and women, not the family lines: it lists no lines.
		$rows = $this->footer->rows($generations, $options, $this->heading($root, $options), !$painted);
		[$cornerWidth, $cornerHeight] = $painted
			? $this->paintedTree->corner(count($generations) - 1, $areaWidth, $treeHeight, count($descendants))
			: $this->tree->corner(count($generations) - 1, $areaWidth, $treeHeight, $upward);
		$footer = '';
		if ($rows !== []) {
			$footerSize = min(
				max(self::MinFooterFont, min($areaWidth, $areaHeight) * .011),
				$cornerWidth / $this->footer->width($rows, 1.0),
				$cornerHeight * .9 / (count($rows) * 1.6),
			);
			// The tree to the right leaves only the corners beside the person, on the left: the footer is aligned in it.
			$left = $options->parts->footerLeft();
			$x = $left ? $margin : ($upward ? $margin + $areaWidth : $margin + $cornerWidth);
			$footer = $this->footer->draw($rows, $footerSize, $x, $margin + $areaHeight, false, $left);
		}

		return [$chart, $this->title($root, $options, $margin, $size, $areaWidth, $areaWidth), $footer];
	}

	/**
	 * Heading (the visitor's, or the family of the person) and the subtitle in the top left or top right corner of the
	 * area $areaWidth wide, fitted to $available.
	 */
	private function title(Person $root, PosterOptions $options, float $margin, float $size, float $available, float $areaWidth): string
	{
		if (!$options->parts->heading()) {
			return '';
		}

		$heading = $this->heading($root, $options);
		$subtitle = $this->subtitle($root, $options);
		$left = $options->parts->headingRight()
			? $margin + $areaWidth - $this->title->width($options->style, $heading, $subtitle, $size, $available)
			: $margin;

		return $this->title->draw(
			$options->style,
			$heading,
			$subtitle,
			$left,
			$margin,
			$size,
			$available,
			$this->titlePad($size, $margin),
			new PosterRandom('title|' . $root->slug),
		);
	}

	/**
	 * Width and height of the title fitted to $available (PosterTitle::extent()).
	 *
	 * @return array{float, float}
	 */
	private function titleExtent(Person $root, PosterOptions $options, float $margin, float $size, float $available): array
	{
		return $this->title->extent(
			$options->style,
			$this->heading($root, $options),
			$this->subtitle($root, $options),
			$size,
			$available,
			$this->titlePad($size, $margin),
		);
	}

	private function heading(Person $root, PosterOptions $options): string
	{
		return $options->title !== '' ? $options->title : $this->heading->of($root);
	}

	/** The person and the number of generations, as far as the visitor wants them; empty without either. */
	private function subtitle(Person $root, PosterOptions $options): string
	{
		return implode(' · ', array_filter([
			$options->parts->person() ? 'Předci osoby ' . $root->name : '',
			$options->parts->generations() ? $this->view->plural($options->depth, 'generace', 'generace', 'generací') : '',
		], static fn (string $part): bool => $part !== ''));
	}

	/** The scroll's rolls reach 1.3 × the padding into the margin, clear of the corner medallion (PosterBorder::Corner). */
	private function titlePad(float $size, float $margin): float
	{
		return min($size * .4, $margin * .22);
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
