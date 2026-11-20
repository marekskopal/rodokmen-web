<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/**
 * Which parts of the poster the visitor wants besides the chart: the heading with its subtitle (the person and the
 * number of generations) and the footer (the legend, the heading and the date). Everything is shown by default.
 * A part of the heading or of the footer counts only with the heading or the footer itself (heading(), person(), …).
 *
 * In the query the parts travel as checkboxes, which a form sends only when they are ticked: the form adds the
 * marker Marker, and with it a missing part is turned off. Without the marker (a plain link) all parts are shown.
 */
final readonly class PosterParts
{
	public const Marker = 'casti';

	/** Query names of the parts, in the order of the constructor. */
	public const Names = ['nadpis-zobrazit', 'nadpis-osoba', 'nadpis-generace', 'paticka-zobrazit', 'paticka-nazev', 'paticka-datum', 'paticka-legenda'];

	public function __construct(
		private bool $heading = true,
		private bool $person = true,
		private bool $generations = true,
		private bool $footer = true,
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

		return new self(...array_map(static fn (string $name): bool => isset($query[$name]), self::Names));
	}

	/**
	 * The parts as query parameters: none when all are shown, else the marker and the shown ones.
	 *
	 * @return array<string, string>
	 */
	public function query(): array
	{
		$flags = array_combine(self::Names, $this->flags());
		if (!in_array(false, $flags, true)) {
			return [];
		}

		return [self::Marker => '1'] + array_map(static fn (): string => '1', array_filter($flags));
	}

	/** Whether the checkbox of the part (one of Names) is ticked, also when its heading or footer is hidden. */
	public function ticked(string $name): bool
	{
		return array_combine(self::Names, $this->flags())[$name] ?? false;
	}

	public function heading(): bool
	{
		return $this->heading;
	}

	public function person(): bool
	{
		return $this->heading && $this->person;
	}

	public function generations(): bool
	{
		return $this->heading && $this->generations;
	}

	public function footer(): bool
	{
		return $this->footer;
	}

	public function name(): bool
	{
		return $this->footer && $this->name;
	}

	public function date(): bool
	{
		return $this->footer && $this->date;
	}

	public function legend(): bool
	{
		return $this->footer && $this->legend;
	}

	/** @return list<bool> */
	private function flags(): array
	{
		return [$this->heading, $this->person, $this->generations, $this->footer, $this->name, $this->date, $this->legend];
	}
}
