<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Support;

/**
 * Lines (rody) and places of the demo family (DemoFamily). Every great-grandparent (ahnentafel 8–15) starts a line
 * with its surname; parents and grandparents belong to their paternal line, the home person to none.
 */
final class DemoLines
{
	/** k => [slug, name, colour, description] */
	public const Lines = [
		8 => ['skopalovi', 'Skopalovi', '#8a5a44', 'Kovářský rod z Uničova, doložený od poloviny 18. století.'],
		9 => ['novotni', 'Novotní', '#5f7a52', 'Domkáři z Uničova a okolí.'],
		10 => ['horakovi', 'Horákovi', '#4f6d8f', 'Litovelští sládci po tři generace.'],
		11 => ['kucerovi', 'Kučerovi', '#9a7b2f', 'Rolníci z Náměště na Hané.'],
		12 => ['dvorakovi', 'Dvořákovi', '#7a4f7f', 'Sedláci z Kostelce na Hané.'],
		13 => ['veseli', 'Veselí', '#3f7f7a', 'Mlynáři z Konice – zatím jen z cizího rodokmenu.'],
		14 => ['prochazkovi', 'Procházkovi', '#a0573c', 'Tesaři z Plumlova.'],
		15 => ['krejci', 'Krejčí', '#6b6b9a', 'Plumlovští krejčí.'],
	];

	/** name => [lat, lon]: real towns of Haná, so that the map makes sense */
	private const Places = [
		'Olomouc' => [49.5938, 17.2509],
		'Prostějov' => [49.4719, 17.1118],
		'Litovel' => [49.7012, 17.0762],
		'Uničov' => [49.7709, 17.1214],
		'Šternberk' => [49.7305, 17.2989],
		'Náměšť na Hané' => [49.6023, 17.0665],
		'Senice na Hané' => [49.6233, 17.0848],
		'Kostelec na Hané' => [49.5137, 17.0584],
		'Konice' => [49.5892, 16.8890],
		'Plumlov' => [49.4664, 17.0151],
	];

	/** Line of person $k (8–15), null for the home person. */
	public static function of(int $k): ?int
	{
		$gen = DemoNames::generation($k);
		if ($gen === 0) {
			return null;
		}

		return $gen >= 3 ? $k >> $gen - 3 : $k << 3 - $gen;
	}

	public static function slugOf(int $k): ?string
	{
		$line = self::of($k);

		return $line !== null ? self::Lines[$line][0] : null;
	}

	/** @return array<string, array{k: int, slug: string, name: string, desc: string, color: string, root: string, people: list<string>, gens: array<string, int>}> */
	public static function lines(): array
	{
		$lines = [];
		foreach (self::Lines as $k => [$slug, $name, $color, $description]) {
			$members = array_values(array_filter(array_keys(DemoFamily::People), static fn (int $n): bool => self::of($n) === $k));
			$gens = [];
			foreach ($members as $n) {
				$gens[DemoFamily::slug($n)] = DemoNames::generation($n);
			}

			$lines[$slug] = [
				'k' => $k,
				'slug' => $slug,
				'name' => $name,
				'desc' => $description,
				'color' => $color,
				'root' => DemoFamily::slug(self::root($k, $members)),
				'people' => array_map(DemoFamily::slug(...), $members),
				'gens' => $gens,
			];
		}

		return $lines;
	}

	/** @return list<array{name: string, lat: float, lon: float, lines: list<array{k: int, people: list<string>}>, years: list<int>, hypothesis: bool}> */
	public static function places(): array
	{
		$places = [];
		foreach (self::Places as $name => [$lat, $lon]) {
			$groups = [];
			$years = [];
			foreach (DemoFamily::People as $k => [, , $born, $died, $place]) {
				if ($place === $name) {
					$groups[self::of($k) ?? 0][] = DemoFamily::slug($k);
					$years = [...$years, ...array_values(array_filter([$born, $died], static fn (?int $y): bool => $y !== null))];
				}
			}

			if ($groups === []) {
				continue;
			}

			$places[] = [
				'name' => $name,
				'lat' => $lat,
				'lon' => $lon,
				'lines' => array_map(
					static fn (int $line, array $people): array => ['k' => $line, 'people' => $people],
					array_keys($groups),
					array_values($groups),
				),
				'years' => $years !== [] ? [min($years), max($years)] : [],
				// Konice is known only from somebody else's tree.
				'hypothesis' => $name === 'Konice',
			];
		}

		return $places;
	}

	/**
	 * The oldest known man of the line who still bears its surname (descends from $k through fathers only).
	 *
	 * @param list<int> $members
	 */
	private static function root(int $k, array $members): int
	{
		$root = $k;
		foreach ($members as $n) {
			if ($n > $root && self::isPaternal($n, $k)) {
				$root = $n;
			}
		}

		return $root;
	}

	private static function isPaternal(int $n, int $k): bool
	{
		for (; $n > $k; $n = intdiv($n, 2)) {
			if ($n % 2 === 1) {
				return false;
			}
		}

		return $n === $k;
	}
}
