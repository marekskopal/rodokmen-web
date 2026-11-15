<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

use DateTimeInterface;

/**
 * Converts dates as the skill writes them ("14. 2. 1890", "3/1867", "1797", "asi 1720", "před 1740") to GEDCOM
 * dates ("14 FEB 1890", "MAR 1867", "1797", "ABT 1720", "BEF 1740"); anything else becomes a date phrase "(text)".
 */
final class GedcomDate
{
	/** A date at the start of a fact: optional qualifier, then day. month. year, month/year or a year. */
	public const Pattern = '(?:(?:asi|kolem|okolo|cca\.?|před|po)\s+)?(?:\d{1,2}\.\s*\d{1,2}\.\s*\d{4}|\d{1,2}\/\d{4}|\d{4})';

	private const Months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

	private const Qualifiers = ['asi' => 'ABT', 'kolem' => 'ABT', 'okolo' => 'ABT', 'cca' => 'ABT', 'cca.' => 'ABT', 'před' => 'BEF', 'po' => 'AFT'];

	/** A calendar date, e.g. "15 NOV 2026". */
	public static function of(DateTimeInterface $date): string
	{
		return (int) $date->format('j') . ' ' . self::Months[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
	}

	public static function fromCzech(string $text): ?string
	{
		$text = trim($text);
		if ($text === '' || $text === '?') {
			return null;
		}

		$original = $text;
		$qualifier = '';
		if (preg_match('/^(\S+)\s+(.+)$/u', $text, $m) === 1 && isset(self::Qualifiers[mb_strtolower($m[1])])) {
			$qualifier = self::Qualifiers[mb_strtolower($m[1])] . ' ';
			$text = $m[2];
		}

		$date = self::exact($text);

		// A date phrase must not hold parentheses of its own.
		return $date !== null ? $qualifier . $date : '(' . str_replace(['(', ')'], '', $original) . ')';
	}

	private static function exact(string $text): ?string
	{
		if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/', $text, $m) === 1) {
			return self::valid((int) $m[1], (int) $m[2]) ? (int) $m[1] . ' ' . self::Months[(int) $m[2] - 1] . ' ' . $m[3] : null;
		}

		if (preg_match('/^(\d{1,2})\/(\d{4})$/', $text, $m) === 1) {
			return self::valid(1, (int) $m[1]) ? self::Months[(int) $m[1] - 1] . ' ' . $m[2] : null;
		}

		return preg_match('/^\d{4}$/', $text) === 1 ? $text : null;
	}

	private static function valid(int $day, int $month): bool
	{
		return $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12;
	}
}
