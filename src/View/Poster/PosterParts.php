<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Which parts of the poster the visitor wants besides the chart and where: the heading with its subtitle (the person
 * and the number of generations) on the left or the right, and the footer (the legend, the heading and the date) on
 * the left or the right. By default everything is shown, the heading on the left and the footer on the right.
 * A part of the heading or of the footer counts only with the heading or the footer itself (heading(), person(), …).
 *
 * In the query the placements travel as select boxes (Places) and the parts as checkboxes (Names), which a form sends
 * only when they are ticked: the form adds the marker Marker, and with it a missing part is turned off. Without the
 * marker (a plain link) all parts are shown in their default places.
 */
final readonly class PosterParts
{
	public const Marker = 'casti';

	/** Query names of the placements of the heading and the footer. */
	public const Places = ['nadpis-zobrazit', 'paticka-zobrazit'];

	/** Query names of the parts (checkboxes), in the order of the constructor. */
	public const Names = ['nadpis-osoba', 'nadpis-generace', 'paticka-nazev', 'paticka-datum', 'paticka-legenda'];

	public function __construct(
		private PosterPlacement $heading = PosterPlacement::Left,
		private PosterPlacement $footer = PosterPlacement::Right,
		private bool $person = true,
		private bool $generations = true,
		private bool $name = true,
		private bool $date = true,
		private bool $legend = true,
	) {
	}

	/** @param array<mixed> $query */
	public static function fromQuery(array $query): self
	{
		if (!isset($query[self::Marker])) {
			return new self();
		}

		return new self(
			self::placement($query, self::Places[0], PosterPlacement::Left),
			self::placement($query, self::Places[1], PosterPlacement::Right),
			...array_map(static fn (string $name): bool => isset($query[$name]), self::Names),
		);
	}

	/**
	 * The parts as query parameters: none when all are shown in their default places, else the marker, the
	 * placements and the shown parts.
	 *
	 * @return array<string, string>
	 */
	public function query(): array
	{
		if (
			$this->heading === PosterPlacement::Left
			&& $this->footer === PosterPlacement::Right
			&& !in_array(false, $this->flags(), true)
		) {
			return [];
		}

		return [self::Marker => '1']
			+ array_combine(self::Places, [$this->heading->value, $this->footer->value])
			+ array_map(static fn (): string => '1', array_filter(array_combine(self::Names, $this->flags())));
	}

	/** Whether the checkbox of the part (one of Names) is ticked, also when its heading or footer is hidden. */
	public function ticked(string $name): bool
	{
		return array_combine(self::Names, $this->flags())[$name] ?? false;
	}

	/** Where the select box of the heading or the footer (one of Places) points. */
	public function place(string $name): PosterPlacement
	{
		return $name === self::Places[0] ? $this->heading : $this->footer;
	}

	public function heading(): bool
	{
		return $this->heading !== PosterPlacement::Off;
	}

	/** Whether the heading goes to the right side (otherwise the left one, when shown at all). */
	public function headingRight(): bool
	{
		return $this->heading === PosterPlacement::Right;
	}

	public function person(): bool
	{
		return $this->heading() && $this->person;
	}

	public function generations(): bool
	{
		return $this->heading() && $this->generations;
	}

	public function footer(): bool
	{
		return $this->footer !== PosterPlacement::Off;
	}

	/** Whether the footer goes to the left side (otherwise the right one, when shown at all). */
	public function footerLeft(): bool
	{
		return $this->footer === PosterPlacement::Left;
	}

	public function name(): bool
	{
		return $this->footer() && $this->name;
	}

	public function date(): bool
	{
		return $this->footer() && $this->date;
	}

	public function legend(): bool
	{
		return $this->footer() && $this->legend;
	}

	/**
	 * A placement from the query; a form's missing select box (an old link with a checkbox) turns it off, a value that
	 * is not a placement (the old checkbox's "1") keeps the default.
	 *
	 * @param array<mixed> $query
	 */
	private static function placement(array $query, string $name, PosterPlacement $default): PosterPlacement
	{
		$value = $query[$name] ?? null;
		if ($value === null) {
			return PosterPlacement::Off;
		}

		return is_string($value) ? PosterPlacement::tryFrom($value) ?? $default : $default;
	}

	/** @return list<bool> */
	private function flags(): array
	{
		return [$this->person, $this->generations, $this->name, $this->date, $this->legend];
	}
}
