<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Support;

/**
 * Demo family tree for the README screenshots and `make demo` (bin/demo-data.php). The home person is the author
 * of the website, Marek Skopal – only his name, no facts about him; every other person, date, register entry and
 * story is invented. The other families have common Czech surnames, the places are real towns of Haná so that the
 * map makes sense.
 *
 * People are numbered as in the ahnentafel: 1 the home person, 2k the father of k, 2k + 1 the mother. Lines and
 * places: DemoLines, register entries: DemoRecords, naming: DemoNames.
 *
 * @phpstan-import-type Record from DemoRecords
 */
final class DemoFamily
{
	/**
	 * k => [name, sex, birth year, death year, place, occupation, status]; status: doc, prob, fam, mh.
	 */
	public const People = [
		// No place: the home person is real and must not appear on the map.
		1 => ['Marek Skopal', 'M', null, null, '', '', 'fam'],
		2 => ['Pavel Skopal', 'M', 1952, 2019, 'Olomouc', 'strojní inženýr', 'fam'],
		3 => ['Jana Dvořáková', 'F', 1955, null, 'Prostějov', 'učitelka', 'fam'],
		4 => ['Josef Skopal', 'M', 1921, 1990, 'Litovel', 'zámečník', 'doc'],
		5 => ['Marie Horáková', 'F', 1924, 2008, 'Litovel', 'švadlena', 'doc'],
		6 => ['František Dvořák', 'M', 1925, 1997, 'Prostějov', 'krejčí', 'doc'],
		7 => ['Ludmila Procházková', 'F', 1928, 2012, 'Prostějov', 'v domácnosti', 'doc'],
		8 => ['Antonín Skopal', 'M', 1889, 1951, 'Uničov', 'kovář', 'doc'],
		9 => ['Anna Novotná', 'F', 1893, 1960, 'Uničov', 'v domácnosti', 'doc'],
		10 => ['Jan Horák', 'M', 1890, 1944, 'Litovel', 'sládek', 'doc'],
		11 => ['Františka Kučerová', 'F', 1894, 1970, 'Náměšť na Hané', 'v domácnosti', 'doc'],
		12 => ['Karel Dvořák', 'M', 1897, 1965, 'Kostelec na Hané', 'rolník', 'doc'],
		13 => ['Marie Veselá', 'F', 1899, 1979, 'Kostelec na Hané', 'v domácnosti', 'doc'],
		14 => ['Václav Procházka', 'M', 1896, 1962, 'Plumlov', 'tesař', 'doc'],
		15 => ['Rozálie Krejčí', 'F', 1901, 1985, 'Plumlov', 'v domácnosti', 'prob'],
		16 => ['Jan Skopal', 'M', 1858, 1920, 'Uničov', 'kovář', 'doc'],
		17 => ['Terezie Svobodová', 'F', 1862, 1931, 'Šternberk', 'v domácnosti', 'doc'],
		18 => ['Josef Novotný', 'M', 1861, 1925, 'Uničov', 'domkář', 'doc'],
		19 => ['Johana Pokorná', 'F', 1866, 1939, 'Uničov', 'v domácnosti', 'doc'],
		20 => ['Tomáš Horák', 'M', 1859, 1915, 'Litovel', 'sládek', 'doc'],
		21 => ['Kateřina Marešová', 'F', 1863, 1922, 'Litovel', 'v domácnosti', 'prob'],
		22 => ['Martin Kučera', 'M', 1862, 1930, 'Náměšť na Hané', 'rolník', 'doc'],
		23 => ['Barbora Němcová', 'F', 1867, 1941, 'Senice na Hané', 'v domácnosti', 'doc'],
		24 => ['Václav Dvořák', 'M', 1866, 1928, 'Kostelec na Hané', 'rolník', 'doc'],
		25 => ['Anna Černá', 'F', 1870, 1944, 'Kostelec na Hané', 'v domácnosti', 'doc'],
		26 => ['Jakub Veselý', 'M', 1864, 1919, 'Konice', 'mlynář', 'mh'],
		27 => ['Marie Fialová', 'F', 1868, 1936, 'Konice', 'v domácnosti', 'mh'],
		28 => ['Matěj Procházka', 'M', 1865, 1927, 'Plumlov', 'tesař', 'doc'],
		29 => ['Veronika Šťastná', 'F', 1869, 1940, 'Plumlov', 'v domácnosti', 'doc'],
		30 => ['Ignác Krejčí', 'M', 1870, 1932, 'Plumlov', 'krejčí', 'prob'],
		32 => ['Václav Skopal', 'M', 1823, 1880, 'Uničov', 'kovář', 'doc'],
		33 => ['Marie Zemanová', 'F', 1828, 1891, 'Uničov', 'v domácnosti', 'doc'],
		34 => ['Ondřej Svoboda', 'M', 1830, 1899, 'Šternberk', 'tkadlec', 'doc'],
		36 => ['Jiří Novotný', 'M', 1832, 1902, 'Uničov', 'domkář', 'prob'],
		40 => ['Antonín Horák', 'M', 1828, 1888, 'Litovel', 'sládek', 'doc'],
		44 => ['František Kučera', 'M', 1831, 1897, 'Náměšť na Hané', 'rolník', 'doc'],
		48 => ['Jan Dvořák', 'M', 1834, 1901, 'Kostelec na Hané', 'rolník', 'doc'],
		49 => ['Kateřina Benešová', 'F', 1838, 1905, 'Kostelec na Hané', 'v domácnosti', 'doc'],
		56 => ['Josef Procházka', 'M', 1833, 1890, 'Plumlov', 'tesař', 'doc'],
		64 => ['Jakub Skopal', 'M', 1789, 1850, 'Uničov', 'kovář', 'doc'],
		65 => ['Anna Králová', 'F', 1795, 1860, 'Uničov', 'v domácnosti', 'prob'],
		128 => ['Matouš Skopal', 'M', 1756, 1821, 'Uničov', 'kovářský mistr', 'mh'],
	];

	/** @return array<string, mixed> site.json as exported by the skill's tools/export_web.py */
	public static function siteData(): array
	{
		$records = DemoRecords::all();
		$people = [];
		$families = [];
		foreach (array_keys(self::People) as $k) {
			$people[self::slug($k)] = self::person($k, $records);
			if (isset(self::People[2 * $k]) || isset(self::People[2 * $k + 1])) {
				$families[self::familyId($k)] = self::family($k);
			}
		}

		return [
			'config' => [
				'nazev' => 'Rodokmen Skopalových',
				'popis' => 'Ukázkový rodokmen: kromě jména autora jsou všechny osoby, data a zápisy smyšlené.',
				'zapati' => 'Ukázková data rodokmen-web – smyšlená rodina.',
				'kontakt' => ['jmeno' => 'Marek Skopal', 'email' => ''],
				'pribeh' => ['menu' => 'Příběh Skopalových', 'rod' => 8, 'tlacitko' => 'Přečíst příběh Skopalových'],
				'maPribeh' => true,
			],
			'root' => self::slug(1),
			'aliases' => [],
			'stats' => [
				'ancestors' => count(self::People) - 1,
				'documented' => count(array_filter(self::People, static fn (array $p): bool => $p[6] === 'doc')),
				'records' => count($records),
				'generations' => max(array_map(DemoNames::generation(...), array_keys(self::People))),
			],
			'people' => $people,
			'families' => $families,
			'records' => $records,
			'lines' => DemoLines::lines(),
			'places' => DemoLines::places(),
			'issues' => [
				['title' => 'Odkud přišel Matouš Skopal', 'text' => 'Zápis o sňatku v Uničově (1786) uvádí jen „cizí kovářský tovaryš“. Hledáme v matrikách Šternberka a Libiny.'],
				['title' => 'Rodiče Rozálie Krejčí', 'text' => 'Křestní zápis v Plumlově chybí – matrika z let 1900–1902 je poškozená.'],
				['title' => 'Datum úmrtí Jana Horáka', 'text' => 'Vyřešeno: zemřel 14. 3. 1944 v Litovli, zápis v matrice zemřelých.'],
			],
			'hypotheses' => [
				[
					'line' => 13,
					'title' => 'Mlynáři Veselí z Konice',
					'text' => 'Údaje o Jakubovi Veselém a Marii Fialové pocházejí z cizího rodokmenu na internetu, matriky je zatím nepotvrdily.',
					'items' => [['name' => 'Jakub Veselý (1864)', 'text' => 'mlynář v Konici, podle cizího stromu syn Jana Veselého']],
					'records' => [],
				],
			],
			'docs' => [],
		];
	}

	/** @return array<string, string> markdown notes: file name (without .md) => text */
	public static function notes(): array
	{
		return [
			'zajimavosti' => <<<'MD'
				# Zajímavosti

				## Pět generací kovářů
				Skopalovi kovali v Uničově od Matouše (1756) až po Antonína († 1951). Kovárna stála u Litovelské brány.

				## Sládci z Litovle
				Tři generace Horáků vařily pivo v litovelském pivovaru; Jan Horák v něm pracoval až do své smrti v roce 1944.

				## Svatba v květnu
				Josef Skopal a Marie Horáková se vzali 13. 5. 1946 – v květnu jako před 63 lety Josefovi prarodiče Jan a Terezie.
				MD,
			'pribeh' => <<<'MD'
				---
				title: Příběh Skopalových
				lead: 'Pět generací kovářů z Uničova – a jak se rod ve 20. století rozešel do světa.'
				---

				## Kovárna u Litovelské brány (1756–1951)
				Nejstarší známý předek, [Matouš Skopal](/osoba/matous-skopal-1756/), přišel do Uničova jako kovářský tovaryš a oženil se s dcerou
				místního mistra. Kovárnu po něm převzal syn Jakub a po něm další tři generace.

				## Do světa (20. století)
				Ve 20. století se rod rozešel do světa: [Josef Skopal](/osoba/josef-skopal-1921/) se vyučil zámečníkem a usadil se v Litovli
				([křest](/zdroj/litovel_krest_josef_skopal_1921/)),
				jeho syn **Pavel** vystudoval strojní fakultu a odešel do Olomouce.

				Mateřské linie vedou na jih Hané – k sedlákům Dvořákům do Kostelce a k tesařům Procházkovým do Plumlova.
				MD,
			'pribeh-horakovi' => <<<'MD'
				---
				title: Příběh Horáků
				lead: 'Tři generace sládků v litovelském pivovaru.'
				---

				## Sládci z Litovle (1828–1944)
				[Antonín Horák](/osoba/antonin-horak-1828/) přišel do Litovle jako pivovarský tovaryš. Jeho syn i vnuk
				zůstali u kotlů – Jan Horák vařil pivo až do své smrti v roce 1944.
				MD,
			'denik' => <<<'MD'
				# Deník hledání

				| Datum | Co se hledalo | Výsledek |
				|---|---|---|
				| 3. 2. 2026 | Křest Antonína Skopala, Uničov 1889 | nalezeno, sken 18 |
				| 5. 2. 2026 | Sňatek Jana Skopala a Terezie Svobodové | nalezeno, Uničov 1883 |
				| 9. 2. 2026 | Rodiče Rozálie Krejčí | matrika poškozená, hledá se jinde |
				| 14. 2. 2026 | Mlynáři Veselí, Konice | jen cizí rodokmen, zápis zatím nenalezen |
				MD,
		];
	}

	public static function slug(int $k): string
	{
		return DemoNames::slug(self::People[$k][0], self::People[$k][2]);
	}

	/**
	 * @param list<Record> $records
	 * @return array<string, mixed>
	 */
	private static function person(int $k, array $records): array
	{
		[$name, $sex, $born, $died, , , $status] = self::People[$k];
		$slug = self::slug($k);
		$parents = self::parents($k);
		$child = $k > 1 ? self::slug(intdiv($k, 2)) : null;
		$path = [];
		for ($n = $k; $n >= 1; $n = intdiv($n, 2)) {
			$path[] = self::slug($n);
		}

		return [
			'k' => $k,
			'slug' => $slug,
			'name' => $name,
			'y' => DemoNames::years($born, $died),
			'years' => DemoNames::years($born, $died),
			'st' => $status,
			'status' => DemoNames::statusText($status),
			'gen' => DemoNames::generation($k),
			'sex' => $sex,
			'rel' => DemoNames::relation($k, $sex),
			'line' => DemoLines::of($k),
			'facts' => self::facts($k),
			'note' => '',
			'records' => array_values(array_map(
				static fn (array $r): string => $r['key'],
				array_filter($records, static fn (array $r): bool => in_array($slug, $r['people'], true)),
			)),
			'father' => $parents[0],
			'mother' => $parents[1],
			'fatherUnknown' => false,
			'motherUnknown' => false,
			'child' => $child,
			'spouse' => $k > 1 && isset(self::People[$k ^ 1]) ? self::slug($k ^ 1) : null,
			'family' => $parents !== [null, null] ? self::familyId($k) : null,
			'ownFamily' => $k > 1 ? self::familyId(intdiv($k, 2)) : null,
			'path' => $path,
			'side' => self::siblings($k),
			'docs' => [],
			'children' => $child !== null ? [$child] : [],
			'also' => [],
			'lineSlug' => DemoLines::slugOf($k),
		];
	}

	/** @return list<string> the timeline: birth, marriage (of the husband), occupation, death */
	private static function facts(int $k): array
	{
		[, , $born, $died, $place, $occupation] = self::People[$k];
		$wife = $k > 1 && $k % 2 === 0 ? self::People[$k + 1][0] ?? null : null;

		return array_values(array_filter([
			$born !== null ? '* ' . $born . ' ' . $place : null,
			$born !== null && $wife !== null ? '× ' . ($born + 25) . ' ' . $place . ', ' . $wife : null,
			$occupation !== '' ? $occupation : null,
			$died !== null ? '† ' . $died . ' ' . $place : null,
		], static fn (?string $fact): bool => $fact !== null));
	}

	/**
	 * The family in which person $k is the child.
	 *
	 * @return array<string, mixed>
	 */
	private static function family(int $k): array
	{
		[$father, $mother] = self::parents($k);

		return [
			'id' => self::familyId($k),
			'father' => $father,
			'mother' => $mother,
			'fatherUnknown' => false,
			'child' => self::slug($k),
			'children' => [self::slug($k)],
			'marriage' => [],
			'records' => [],
			'line' => DemoLines::of(2 * $k),
			'gen' => DemoNames::generation(2 * $k),
			'side' => self::siblings($k),
		];
	}

	/** @return array{?string, ?string} slugs of the father and the mother, null when unknown */
	private static function parents(int $k): array
	{
		return [
			isset(self::People[2 * $k]) ? self::slug(2 * $k) : null,
			isset(self::People[2 * $k + 1]) ? self::slug(2 * $k + 1) : null,
		];
	}

	/** @return list<array{name: string, text: string, records: list<string>, rel: string, y: string, unsure: bool}> */
	private static function siblings(int $k): array
	{
		[$name, $sex, $born] = self::People[$k];
		if ($born === null || $k < 2 || $k % 3 !== 0) {
			return [];
		}

		$surname = explode(' ', $name)[1];
		$male = $sex === 'F' ? DemoNames::male($surname) : $surname;

		return [
			[
				'name' => 'Josef ' . $male . ' (* ' . ($born + 2) . ')',
				'text' => 'Mladší bratr, zemřel v dětství.',
				'records' => [],
				'rel' => 'sourozenec',
				'y' => (string) ($born + 2),
				'unsure' => false,
			],
			[
				'name' => 'Anna ' . DemoNames::female($male) . ' (* ' . ($born + 4) . ')',
				'text' => 'Mladší sestra, provdaná do sousední obce.',
				'records' => [],
				'rel' => 'sourozenec',
				'y' => (string) ($born + 4),
				'unsure' => false,
			],
		];
	}

	private static function familyId(int $k): string
	{
		return 'rodina-' . self::slug($k);
	}
}
