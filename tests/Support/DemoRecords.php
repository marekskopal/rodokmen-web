<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Support;

/**
 * Invented register entries of the demo family (DemoFamily): a baptism of every documented person born before
 * 1926, a marriage of every documented man with his wife and a death before 1940.
 *
 * @phpstan-type Record array{key: string, kind: string, date: string, title: string, summary: string, scan: int,
 *     archive: string, volume: string, vdesc: string, portal: string, url: string, img: bool, full: bool,
 *     boxes: list<list<float>>, orig: string, cs: string, kindName: string, people: list<string>}
 */
final class DemoRecords
{
	private const Archive = 'Zemský archiv v Opavě';

	private const Latin = ['Jan' => 'Joannes', 'Josef' => 'Josephus', 'Antonín' => 'Antonius', 'Václav' => 'Wenceslaus', 'Marie' => 'Maria'];

	/** @return list<Record> */
	public static function all(): array
	{
		$records = [];
		foreach (array_keys(DemoFamily::People) as $k) {
			$records = [...$records, ...self::of($k)];
		}

		return $records;
	}

	/** @return list<Record> */
	private static function of(int $k): array
	{
		[$name, $sex, $born, $died, $place, , $status] = DemoFamily::People[$k];
		if ($born === null || $status !== 'doc' || $born > 1925) {
			return [];
		}

		$records = [self::baptism($k, $name, $sex, $born, $place)];
		if ($sex === 'M' && isset(DemoFamily::People[$k + 1])) {
			$records[] = self::marriage($k, $name, $born + 25, $place);
		}
		if ($died !== null && $died < 1940) {
			$records[] = self::death($k, $name, $sex, $born, $died, $place);
		}

		return $records;
	}

	/** @return Record */
	private static function baptism(int $k, string $name, string $sex, int $born, string $place): array
	{
		$first = explode(' ', $name)[0];
		$volume = 'N ' . $place . ' ' . self::decade($born);

		return self::record(
			key: DemoNames::recordKey($place, 'krest', $name, $born),
			kind: 'N',
			date: self::date($k, 7, 5, $born),
			title: 'Křest – ' . $name,
			summary: $name . ', ' . ($sex === 'F' ? 'dcera' : 'syn') . '; ' . self::parents($k) . '; ' . $place . '.',
			scan: 10 + $k,
			volume: $volume,
			url: 'krest-' . DemoFamily::slug($k),
			orig: (self::Latin[$first] ?? $first) . ', legitimus, catholicus',
			cs: $first . ', manželský, katolík',
			people: [DemoFamily::slug($k)],
		);
	}

	/** @return Record */
	private static function marriage(int $k, string $name, int $year, string $place): array
	{
		$wife = DemoFamily::People[$k + 1][0];

		return self::record(
			key: DemoNames::recordKey($place, 'snatek', $name, $year),
			kind: 'O',
			date: self::date($k, 3, 7, $year),
			title: 'Sňatek – ' . $name . ' a ' . $wife,
			summary: $name . ', ' . DemoFamily::People[$k][5] . ', a ' . $wife . ', oddáni v obci ' . $place . '.',
			scan: 100 + $k,
			volume: 'O ' . $place . ' ' . self::decade($year),
			url: 'snatek-' . DemoFamily::slug($k),
			orig: 'copulati sunt',
			cs: 'byli oddáni',
			people: [DemoFamily::slug($k), DemoFamily::slug($k + 1)],
		);
	}

	/** @return Record */
	private static function death(int $k, string $name, string $sex, int $born, int $died, string $place): array
	{
		$ending = $sex === 'F' ? 'a' : '';

		return self::record(
			key: DemoNames::recordKey($place, 'umrti', $name, $died),
			kind: 'Z',
			date: self::date($k, 11, 3, $died),
			title: 'Úmrtí – ' . $name,
			summary: $name . ' zemřel' . $ending . ' v obci ' . $place . ' ve věku ' . ($died - $born) . ' let.',
			scan: 200 + $k,
			volume: 'Z ' . $place . ' ' . self::decade($died),
			url: 'umrti-' . DemoFamily::slug($k),
			orig: 'obiit',
			cs: 'zemřel' . $ending,
			people: [DemoFamily::slug($k)],
		);
	}

	/**
	 * @param list<string> $people
	 * @return Record
	 */
	private static function record(
		string $key,
		string $kind,
		string $date,
		string $title,
		string $summary,
		int $scan,
		string $volume,
		string $url,
		string $orig,
		string $cs,
		array $people,
	): array
	{
		return [
			'key' => $key,
			'kind' => $kind,
			'date' => $date,
			'title' => $title,
			'summary' => $summary,
			'scan' => $scan,
			'archive' => self::Archive,
			'volume' => $volume,
			'vdesc' => $volume,
			'portal' => 'Matriky online',
			'url' => 'https://example.org/matriky/' . $url,
			'img' => false,
			'full' => false,
			'boxes' => [],
			'orig' => $orig,
			'cs' => $cs,
			'kindName' => ['N' => 'Křest', 'O' => 'Sňatek', 'Z' => 'Úmrtí'][$kind] ?? '',
			'people' => $people,
		];
	}

	/** An invented but stable day and month for person $k. */
	private static function date(int $k, int $dayFactor, int $monthFactor, int $year): string
	{
		return (($k * $dayFactor) % 27 + 1) . '. ' . (($k * $monthFactor) % 12 + 1) . '. ' . $year;
	}

	private static function decade(int $year): string
	{
		$start = intdiv($year, 10) * 10;

		return $start . '–' . ($start + 9);
	}

	private static function parents(int $k): string
	{
		$father = DemoFamily::People[2 * $k][0] ?? null;
		$mother = DemoFamily::People[2 * $k + 1][0] ?? null;

		// The register's style "otec …, matka …" avoids declining the names.
		return match (true) {
			$father !== null && $mother !== null => 'otec ' . $father . ', matka ' . $mother,
			$father !== null => 'otec ' . $father,
			default => 'rodiče neuvedeni',
		};
	}
}
