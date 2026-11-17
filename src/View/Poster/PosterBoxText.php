<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\View\FanGeometry;
use Rodokmen\Site\View\FanLabels;
use Rodokmen\Site\View\FanYears;
use Rodokmen\Site\View\View;
use const INF;

/**
 * Names and years in the boxes of the poster tree. All boxes of a generation write them the same way – across or
 * along the box, on one or two lines, whichever gives the larger letters to the name that needs the most room – and
 * with the same size, so that a generation looks even.
 */
final readonly class PosterBoxText
{
	/** Boxes write their text along themselves only when that gives letters this much larger. */
	private const AlongBonus = 1.15;

	/** Size of the years relative to the name when they have a line of their own. */
	private const YearsSize = .8;

	public function __construct(private View $view, private FanGeometry $geometry, private FanLabels $labels, private FanYears $years,)
	{
	}

	/** @param list<Person> $people the ancestors of one generation, in boxes of $width × $height */
	public function fit(array $people, float $width, float $height, float $maxFont): BoxText
	{
		[$across, $acrossTwo] = $this->fitAlong($people, $width, $height);
		[$along, $alongTwo] = $this->fitAlong($people, $height, $width);

		return $along > $across * self::AlongBonus
			? new BoxText(min($maxFont, $along), $alongTwo, true)
			: new BoxText(min($maxFont, $across), $acrossTwo, false);
	}

	/** Name and years centred at $cx, $cy. */
	public function write(Person $person, string $class, float $cx, float $cy, BoxText $text): string
	{
		$size = $text->size;
		$years = $this->years->format($person->y);
		$out = sprintf('<g transform="translate(%.1f,%.1f)%s">', $cx, $cy, $text->turned ? ' rotate(-90)' : '');
		if ($text->twoLines && $years !== '') {
			return $out
				. $this->labels->text('pst-t pst-name' . $class, -$size * .2, $size, $person->name)
				. $this->labels->text('pst-t' . $class, $size * .77, $size * self::YearsSize, $years)
				. '</g>';
		}

		return $out . sprintf(
			'<text class="pst-t pst-name%s" y="%.1f" text-anchor="middle" font-size="%.1f">%s%s</text></g>',
			$class,
			$size * .35,
			$size,
			$this->view->e($person->name),
			$years !== '' ? ' <tspan class="pst-years">' . $this->view->e($years) . '</tspan>' : '',
		);
	}

	/**
	 * Largest name size that fits every box with the text running along $length: two lines or one.
	 *
	 * @param list<Person> $people
	 * @return array{float, bool} font size, two lines
	 */
	private function fitAlong(array $people, float $length, float $cross): array
	{
		$room = $length * .9;
		[$one, $two] = [$cross * .7, $cross * .85 / (1 + self::YearsSize * 1.3)];
		foreach ($people as $person) {
			$years = $this->years->format($person->y);
			$name = $this->geometry->fit($person->name, $room, INF);
			$one = min($one, $this->geometry->fit(trim($person->name . ' ' . $years), $room, INF));
			$two = min($two, $years === '' ? $name : min($name, $this->geometry->fit($years, $room, INF) / self::YearsSize));
		}

		return $two >= $one * .85 ? [$two, true] : [$one, false];
	}
}
