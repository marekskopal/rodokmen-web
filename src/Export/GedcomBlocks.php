<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

use Rodokmen\Site\Data\Model\Family;
use Rodokmen\Site\Data\Repository;

/** Structures shared by the GEDCOM records: names, events, notes and source citations. */
final readonly class GedcomBlocks
{
	public function __construct(private Repository $repository)
	{
	}

	public function name(GedcomWriter $out, string $name): void
	{
		[$given, $surname] = FactParser::name($name);
		$out->line(1, 'NAME', trim($given . ($surname !== '' ? ' /' . $surname . '/' : '')));
		if ($given !== '') {
			$out->line(2, 'GIVN', $given);
		}
		if ($surname !== '') {
			$out->line(2, 'SURN', $surname);
		}
	}

	public function event(GedcomWriter $out, GedcomEvent $event): void
	{
		// "1 DEAT Y" says that the event happened when nothing more is known about it.
		$out->line(1, $event->tag, $event->date === null && $event->place === '' && $event->note === '' ? 'Y' : '');
		if ($event->date !== null) {
			$out->line(2, 'DATE', $event->date);
		}
		if ($event->place !== '') {
			$out->line(2, 'PLAC', $event->place);
		}
		if ($event->note !== '') {
			$out->line(2, 'NOTE', $event->note);
		}
	}

	/** @param list<string|null> $notes one NOTE, empty ones skipped */
	public function notes(GedcomWriter $out, array $notes): void
	{
		$text = implode("\n", array_filter($notes, static fn (?string $note): bool => $note !== null && trim($note) !== ''));
		if ($text !== '') {
			$out->line(1, 'NOTE', $text);
		}
	}

	/**
	 * The family's marriage facts; without them the couple's, those naming the wife's surname first (a parent married
	 * twice also has the other marriage among their facts), as the skill's exporter picks them.
	 *
	 * @return list<string>
	 */
	public function marriageFacts(Family $family): array
	{
		if ($family->marriage !== []) {
			return $family->marriage;
		}

		$father = $this->repository->person($family->father);
		$mother = $this->repository->person($family->mother);

		$facts = array_values(array_unique(array_filter(
			[...$father->facts ?? [], ...$mother->facts ?? []],
			static fn (string $fact): bool => FactParser::event($fact)?->tag === 'MARR',
		)));
		$surname = mb_substr($mother !== null ? FactParser::name($mother->name)[1] : '', 0, 5);
		$named = $surname === '' ? [] : array_values(array_filter($facts, static fn (string $fact): bool => str_contains($fact, $surname)));

		return $named !== [] ? $named : $facts;
	}

	/** @param list<string> $keys record keys */
	public function citations(GedcomWriter $out, GedcomIds $ids, array $keys): void
	{
		foreach ($this->repository->recordsByKeys($keys) as $record) {
			$out->pointer(1, 'SOUR', $ids->source($record->key));
			if ($record->scan > 0) {
				$out->line(2, 'PAGE', 'snímek ' . $record->scan);
			}
			$text = trim($record->orig . ($record->cs !== '' ? "\n\nPřeklad: " . $record->cs : ''));
			$date = GedcomDate::fromCzech($record->date);
			if ($text === '' && $date === null) {
				continue;
			}

			$out->line(2, 'DATA');
			if ($date !== null) {
				$out->line(3, 'DATE', $date);
			}
			if ($text !== '') {
				$out->line(3, 'TEXT', $text);
			}
		}
	}
}
