<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\View\FanLabels;
use Rodokmen\Site\View\FanYears;
use Rodokmen\Site\View\View;

/**
 * Names and years on the oval plaques of the painted tree, as large as the oval allows. The middle of an oval is
 * wider than its ends, so a long name may run nearly from end to end as long as its lines are thin – or take a line
 * for the given names and one for the surname, over the years. The corners of every line keep within a share of
 * the rim, given for each axis. All ovals of a generation write the same way, so the one that needs the most room
 * decides.
 */
final readonly class PosterOvalText
{
	/** More lines only for letters at least this much larger. */
	private const MoreLines = 1.05;

	/** Lines are as many letter sizes apart as their letters reach: one line's descenders touch the next one's accents. */
	private const Leading = PosterBoxText::Ascent + PosterBoxText::Descent;

	/** One line, the name over the years, and the given names over the surname over the years. */
	private const Layouts = [[false, false], [true, false], [true, true]];

	public function __construct(
		private View $view,
		private FanLabels $labels,
		private FanYears $years,
		private PosterBoxText $box,
		private PosterTextWidth $widths,
		private PosterPeople $people,
	)
	{
	}

	/**
	 * @param list<Person> $people the ancestors of one generation, in ovals of $width × $height
	 * @param float $insideWidth share of the oval's width that the text keeps within
	 * @param float $insideHeight share of the oval's height that the text keeps within
	 */
	public function fit(
		array $people,
		PosterStyle $style,
		float $width,
		float $height,
		float $maxFont,
		float $insideWidth,
		float $insideHeight,
	): BoxText
	{
		// The half-axes of the ellipse the text keeps within.
		[$rx, $ry] = [$width * $insideWidth / 2, $height * $insideHeight / 2];
		$extents = array_map(
			fn (array $layout): array => $this->extents($people, new BoxText(1.0, $layout[0], false, $layout[1]), $style),
			self::Layouts,
		);
		$across = $this->best($extents, $rx, $ry, false, $maxFont);
		$along = $this->best($extents, $ry, $rx, true, $maxFont);

		return $along->size > $across->size * PosterBoxText::AlongBonus ? $along : $across;
	}

	/** Name and years centred at $cx, $cy. */
	public function write(Person $person, string $class, float $cx, float $cy, BoxText $text): string
	{
		$lines = $this->lines($person, $text);
		if ($lines === null) {
			return $this->box->write($person, $class, $cx, $cy, $text);
		}

		// The name's lines in one text, so that it reads and is found as a whole.
		$size = $text->size;
		$name = [];
		$rest = '';
		foreach ($lines as [$line, $isName, $share, $baseline]) {
			if ($isName) {
				$name[] = sprintf('<tspan x="0" y="%.1f">%s</tspan>', $size * $baseline, $this->view->e($line));
			} else {
				$rest .= $this->labels->text('pst-t' . $class, $size * $baseline, $size * $share, $line);
			}
		}

		return sprintf('<g transform="translate(%.1f,%.1f)%s">', $cx, $cy, $text->turned ? ' rotate(-90)' : '')
			. sprintf('<text class="pst-t pst-name%s" text-anchor="middle" font-size="%.1f">%s</text>', $class, $size, implode(' ', $name))
			. $rest . '</g>';
	}

	/**
	 * The way of writing with the largest letters, the text running along the half-axis $along.
	 *
	 * @param list<list<array{float, float}>> $extents everybody's lines in each of the Layouts, from extents()
	 */
	private function best(array $extents, float $along, float $across, bool $turned, float $maxFont): BoxText
	{
		$best = new BoxText(0.0, false, $turned);
		foreach (self::Layouts as $i => [$twoLines, $split]) {
			// The farthest corner of anybody's lines, in shares of the rim (squared).
			$farthest = 0.0;
			foreach ($extents[$i] ?? [] as [$length, $reach]) {
				$farthest = max($farthest, ($length / 2 / $along) ** 2 + ($reach / $across) ** 2);
			}
			$size = min($maxFont, 1 / sqrt(max(1e-9, $farthest)));
			if ($size > $best->size * self::MoreLines) {
				$best = new BoxText($size, $twoLines, $turned, $split);
			}
		}

		return $best;
	}

	/**
	 * Everybody's lines written with letters 1 high: their length and how far their letters reach from the middle
	 * across them.
	 *
	 * @param list<Person> $people
	 * @return list<array{float, float}>
	 */
	private function extents(array $people, BoxText $text, PosterStyle $style): array
	{
		$extents = [];
		foreach ($people as $person) {
			$split = $this->lines($person, $text);
			$lines = $split === null
				? $this->box->extents($person, $text->twoLines)
				: array_map(static fn (array $line): array => [$line[0], $line[2], $line[4]], $split);
			// Uncertain and unknown ancestors are written in italics (PosterPeople::textClass()).
			$italic = $this->people->textClass($person) !== '';
			foreach ($lines as [$line, $share, $reach]) {
				$extents[] = [$this->widths->of($line, $style, $italic) * $share, $reach];
			}
		}

		return $extents;
	}

	/**
	 * The lines of a name split over two lines, at letters 1 high: their text, whether it is a part of the name,
	 * their size, baseline, and how far their letters reach from the middle across them. Null when the
	 * name is not split (PosterBoxText writes it): not asked to, or nowhere to break it.
	 *
	 * @return ?non-empty-list<array{string, bool, float, float, float}>
	 */
	private function lines(Person $person, BoxText $text): ?array
	{
		// Notes in parentheses after the name ("(?)") go to the line of the years, where they take less room.
		preg_match('/^(.*?)((?:\s*\([^)]*\))*)$/u', trim($person->name), $note);
		[$name, $notes] = [trim($note[1] ?? ''), trim($note[2] ?? '')];
		// Before the birth surname ("Marie Nováková / roz. Malá"), else before the last word ("Jan / Novák").
		if (!$text->split || preg_match('/^(.+?)\s+(roz\.\s+.+|\S+)$/u', $name, $m) !== 1) {
			return null;
		}

		$small = trim($this->years->format($person->y) . ' ' . $notes);
		$parts = [[$m[1], true, 1.0], [$m[2], true, 1.0]];
		if ($small !== '') {
			$parts[] = [$small, false, PosterBoxText::YearsSize];
		}
		// The block of lines centred on the middle.
		$top = -array_sum(array_map(static fn (array $part): float => $part[2] * self::Leading, $parts)) / 2;
		$lines = [];
		foreach ($parts as [$line, $isName, $share]) {
			$bottom = $top + $share * self::Leading;
			$lines[] = [$line, $isName, $share, $top + $share * PosterBoxText::Ascent, max(-$top, $bottom)];
			$top = $bottom;
		}

		return $lines;
	}
}
