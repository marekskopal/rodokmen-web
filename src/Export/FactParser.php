<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

/**
 * Reads the skill's facts (`* 14. 2. 1890 Holice čp. 112`, `× 1919 Holice Josefa Pokorná`, `† 1944 Holice`, free
 * text), names and life spans for the GEDCOM export.
 */
final class FactParser
{
	private const Markers = ['*' => 'BIRT', '×' => 'MARR', '⚭' => 'MARR', '†' => 'DEAT'];

	/** The event in a fact, or null for free text (a trade, a residence). $partner is removed from a marriage. */
	public static function event(string $fact, ?string $partner = null): ?GedcomEvent
	{
		$fact = trim($fact);
		$marker = mb_substr($fact, 0, 1);
		if (isset(self::Markers[$marker])) {
			return self::parse(self::Markers[$marker], $fact, trim(mb_substr($fact, 1)), $partner);
		}

		if (preg_match('/^(?:pokřt\S*|křest)\s+(.*)$/iu', $fact, $m) === 1) {
			return self::parse('CHR', $fact, $m[1], null);
		}

		return null;
	}

	/**
	 * Given names and surname: the last word is the surname ("Jan Novák"), "Marie Nováková roz. Malá" gives the birth
	 * surname; notes in parentheses and question marks are left out.
	 *
	 * @return array{string, string} [given names, surname]
	 */
	public static function name(string $name): array
	{
		$name = trim((string) preg_replace(['/\([^)]*\)/u', '/\?/u', '/\s+/u'], ['', '', ' '], $name));
		if (preg_match('/^(.+?)\s+(?:\S+\s+)?roz\.\s+(.+)$/u', $name, $m) === 1) {
			return [$m[1], $m[2]];
		}

		$space = mb_strrpos($name, ' ');

		return $space === false ? [$name, ''] : [mb_substr($name, 0, $space), mb_substr($name, $space + 1)];
	}

	/**
	 * Birth and death dates of a life span as the skill writes it ("1890–1944", "* 1895", "† 1940", "asi 1720–1785",
	 * "1680 – před 1740", "1990 – ?").
	 *
	 * @return array{?string, ?string} GEDCOM dates [birth, death]
	 */
	public static function lifespan(string $years): array
	{
		$years = trim($years);
		if (str_starts_with($years, '*')) {
			return [GedcomDate::fromCzech(mb_substr($years, 1)), null];
		}

		if (str_starts_with($years, '†')) {
			return [null, GedcomDate::fromCzech(mb_substr($years, 1))];
		}

		$parts = preg_split('/\s*[–—-]\s*/u', $years, 2);
		if ($parts === false || count($parts) < 2) {
			return [GedcomDate::fromCzech($years), null];
		}

		return [GedcomDate::fromCzech($parts[0]), GedcomDate::fromCzech($parts[1])];
	}

	private static function parse(string $tag, string $fact, string $rest, ?string $partner): GedcomEvent
	{
		$date = null;
		if (preg_match('/^(' . GedcomDate::Pattern . ')(?=\s|,|$)\s*,?\s*(.*)$/u', $rest, $m) === 1) {
			$date = GedcomDate::fromCzech($m[1]);
			$rest = $m[2];
		}

		$place = $rest;
		if ($partner !== null && $partner !== '') {
			$at = mb_strpos($place, $partner);
			$place = $at === false ? $place : mb_substr($place, 0, $at);
		}

		// The place is the village or town: without house numbers ("čp. 112") and notes in parentheses.
		$place = trim((string) preg_replace(['/\([^)]*\)/u', '/\bčp\.?\s*\S+/u'], '', $place), " ,\t");
		$place = (string) preg_replace('/\s+/u', ' ', $place);

		return new GedcomEvent($tag, $date, $place, $place === trim($rest) ? '' : $fact);
	}
}
