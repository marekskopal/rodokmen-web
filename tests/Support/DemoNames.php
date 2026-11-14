<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Support;

/**
 * Naming helpers of the demo family (DemoFamily): ahnentafel generations, slugs and record keys as the skill's
 * exporter makes them, and Czech wording.
 */
final class DemoNames
{
	/** Generation of ahnentafel number $k: 0 the home person, 1 the parents, 2 the grandparents… */
	public static function generation(int $k): int
	{
		return strlen(decbin($k)) - 1;
	}

	public static function slug(string $name, ?int $born): string
	{
		$slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', self::ascii($name)), '-');

		return $born !== null ? $slug . '-' . $born : $slug;
	}

	public static function recordKey(string $place, string $kind, string $name, int $year): string
	{
		return trim((string) preg_replace('/[^a-z0-9]+/', '_', self::ascii($place . ' ' . $kind . ' ' . $name . ' ' . $year)), '_');
	}

	public static function years(?int $born, ?int $died): string
	{
		return $born !== null ? $born . ' – ' . ($died ?? '?') : '';
	}

	public static function relation(int $k, string $sex): string
	{
		$gen = self::generation($k);
		$names = [0 => ['výchozí osoba', 'výchozí osoba'], 1 => ['otec', 'matka'], 2 => ['dědeček', 'babička'], 3 => ['pradědeček', 'prababička']];
		if (isset($names[$gen])) {
			return $names[$gen][$sex === 'F' ? 1 : 0];
		}

		return ($gen - 2) . '× pra' . ($sex === 'F' ? 'babička' : 'dědeček');
	}

	public static function statusText(string $status): string
	{
		return [
			'doc' => 'doloženo zápisem v matrice',
			'prob' => 'pravděpodobné, zápis zatím chybí',
			'fam' => 'údaj od rodiny',
			'mh' => 'převzato z cizího rodokmenu',
		][$status] ?? '';
	}

	/** Surname of a brother of a woman (Horáková → Horák, Novotná → Novotný). */
	public static function male(string $surname): string
	{
		return match (true) {
			str_ends_with($surname, 'ová') => mb_substr($surname, 0, -3),
			str_ends_with($surname, 'á') => mb_substr($surname, 0, -1) . 'ý',
			default => $surname,
		};
	}

	/** Surname of a sister of a man (Horák → Horáková, Novotný → Novotná). */
	public static function female(string $surname): string
	{
		return match (true) {
			str_ends_with($surname, 'á') || str_ends_with($surname, 'í') => $surname,
			str_ends_with($surname, 'ý') => mb_substr($surname, 0, -1) . 'á',
			default => $surname . 'ová',
		};
	}

	/** Lower case without Czech diacritics (iconv's transliteration differs between platforms). */
	private static function ascii(string $text): string
	{
		return strtr(mb_strtolower($text), [
			'á' => 'a',
			'č' => 'c',
			'ď' => 'd',
			'é' => 'e',
			'ě' => 'e',
			'í' => 'i',
			'ň' => 'n',
			'ó' => 'o',
			'ř' => 'r',
			'š' => 's',
			'ť' => 't',
			'ú' => 'u',
			'ů' => 'u',
			'ý' => 'y',
			'ž' => 'z',
		]);
	}
}
