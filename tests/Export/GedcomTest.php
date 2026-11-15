<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Export;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Export\Gedcom;
use Rodokmen\Site\Export\GedcomBlocks;
use Rodokmen\Site\Export\GedcomFamilies;
use Rodokmen\Site\Export\GedcomPeople;
use Rodokmen\Site\Tests\Support\DemoFamily;
use Rodokmen\Site\Tests\Support\Fixture;

final class GedcomTest extends TestCase
{
	private string $dir = '';

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testHeaderAndTrailer(): void
	{
		$file = $this->export();

		self::assertStringStartsWith("\u{FEFF}0 HEAD\r\n1 SOUR RODOKMEN-WEB\r\n", $file);
		self::assertStringEndsWith("\r\n0 TRLR\r\n", $file);
		self::assertStringContainsString("1 DATE 15 NOV 2026\r\n", $file);
		self::assertStringContainsString("1 GEDC\r\n2 VERS 5.5.1\r\n2 FORM LINEAGE-LINKED\r\n1 CHAR UTF-8\r\n", $file);
		self::assertStringContainsString("0 @U1@ SUBM\r\n1 NAME Rodokmen Testových\r\n", $file);
		self::assertStringNotContainsString("\n\n", $file);
	}

	public function testAncestorsWithTheirEventsNotesAndSources(): void
	{
		$records = $this->records($this->export());
		$jan = $this->find($records, 'INDI', '1 NAME Jan /Novák/');

		self::assertContains('1 SEX M', $jan);
		self::assertSame(['1 BIRT', '2 DATE 1 MAY 1990', '2 PLAC Praha'], $this->after($jan, '1 BIRT', 3));
		self::assertNotContains('1 MARR', $jan, 'the marriage belongs to the family');
		$note = implode(
			"\n",
			array_filter($jan, static fn (string $l): bool => str_starts_with($l, '1 NOTE') || str_starts_with($l, '2 CONT')),
		);
		self::assertStringContainsString('Věrohodnost: doloženo matrikou', $note);
		self::assertStringContainsString('- Eva Nová (* 1985), nevlastní sourozenec: Nevlastní sestra.', $note);
		self::assertStringContainsString('- Karel Novák: Bratranec.', $note);
		self::assertStringNotContainsString('Petr Novák', $note, 'a full sibling is a person of his own');
		self::assertContains('2 PAGE snímek 12', $jan);
		self::assertContains('3 TEXT Joannes', $jan);
		self::assertContains('4 CONT Překlad: Jan', $jan);

		$josef = $this->find($records, 'INDI', '1 NAME Josef /Novák/');
		self::assertSame(['1 BIRT', '2 DATE 1960'], $this->after($josef, '1 BIRT', 2), 'from the life span');
	}

	public function testFamiliesWithMarriagesAndFullSiblings(): void
	{
		$records = $this->records($this->export());
		$parents = $this->find($records, 'FAM', '1 WIFE @' . $this->id($records, 'Marie /Malá/') . '@');

		self::assertContains('1 HUSB @' . $this->id($records, 'Jan /Novák/') . '@', $parents);
		self::assertContains('1 CHIL @' . $this->id($records, 'Antonín /Novák/') . '@', $parents);
		self::assertSame(
			['1 MARR', '2 DATE 2015', '2 PLAC Praha', '2 NOTE × 2015 Praha, Marie Malá'],
			$this->after($parents, '1 MARR', 4),
		);

		$petr = $this->find($records, 'INDI', '1 NAME Petr /Novák/');
		self::assertSame(['1 BIRT', '2 DATE 1993'], $this->after($petr, '1 BIRT', 2));
		$grandparents = $this->find($records, 'FAM', '1 HUSB @' . $this->id($records, 'Josef /Novák/') . '@');
		self::assertContains('1 CHIL ' . $this->xref($petr), $grandparents);
		self::assertSame(
			[],
			array_filter($records, static fn (array $r): bool => str_contains($r[0], 'INDI') && in_array('1 NAME Eva /Nová/', $r, true)),
			'a half sibling only in the notes',
		);
	}

	public function testAParentNamedByAnAliasAndAnotherMarriage(): void
	{
		$data = Fixture::siteData();
		self::assertIsArray($data['families']);
		self::assertIsArray($data['families']['jan-novak-1990-a-marie-mala-1992']);
		$data['families']['jan-novak-1990-a-marie-mala-1992']['father'] = 'stary-slug';
		$data['families']['jan-novak-1990-a-marie-mala-1992']['marriage'] = ['× 2015 Praha'];
		$records = $this->records($this->export($data));

		$jan = $this->find($records, 'INDI', '1 NAME Jan /Novák/');
		$parents = $this->find($records, 'FAM', '1 HUSB ' . $this->xref($jan));
		self::assertContains('1 FAMS ' . $this->xref($parents), $jan);
		self::assertContains('1 NOTE × 2015 Praha, Marie Malá', array_map(
			static fn (string $l): string => str_starts_with($l, '1 NOTE ') ? '1 NOTE ' . explode("\n", substr($l, 7))[0] : $l,
			$jan,
		), 'a marriage fact the family does not use stays with the person');
	}

	public function testTheMarriageWithTheOtherParentIsChosenAndTheBirthSurnameIsNoPlace(): void
	{
		$data = Fixture::siteData();
		self::assertIsArray($data['people']);
		self::assertIsArray($data['people']['jan-novak-1990']);
		self::assertIsArray($data['people']['marie-mala-1992']);
		$data['people']['jan-novak-1990']['facts'] = ['× 2010 Brno, Eva Dvořáková', '× 2015 Praha Marie Malá'];
		$data['people']['marie-mala-1992']['name'] = 'Marie Nováková roz. Malá';
		$records = $this->records($this->export($data));
		$parents = $this->find($records, 'FAM', '1 HUSB @' . $this->id($records, 'Jan /Novák/') . '@');

		self::assertSame(['1 MARR', '2 DATE 2015', '2 PLAC Praha'], $this->after($parents, '1 MARR', 3));
		self::assertStringNotContainsString('Eva Dvořáková', implode("\n", $parents));
		self::assertContains(
			'1 NOTE × 2010 Brno, Eva Dvořáková',
			$this->find($records, 'INDI', '1 NAME Jan /Novák/'),
			'the other marriage stays with Jan',
		);
	}

	public function testSourceRecords(): void
	{
		$source = $this->find($this->records($this->export()), 'SOUR', '1 TITL Křest Jana Nováka, 1. 5. 1990');

		self::assertContains('1 AUTH Archiv hl. m. Prahy', $source);
		self::assertContains('1 PUBL eBadatelna', $source);
		self::assertContains('2 CONT https://example.org/scan/12', $source);
	}

	public function testEveryPointerLeadsToARecordAndLinksGoBothWays(): void
	{
		foreach ([null, DemoFamily::siteData()] as $data) {
			$file = $this->export($data);
			$records = $this->records($file);
			$byXref = [];
			foreach ($records as $record) {
				$byXref[$this->xref($record)] = $record;
			}

			foreach ($records as $record) {
				$this->assertPointers($record, $byXref);
			}
			self::assertSame(1, substr_count($file, "\r\n0 TRLR\r\n"));
		}
	}

	/**
	 * Every pointer of the record leads to a record, and that record points back (FAMC ↔ CHIL, HUSB/WIFE → FAMS).
	 *
	 * @param non-empty-list<string> $record
	 * @param array<string, non-empty-list<string>> $byXref
	 */
	private function assertPointers(array $record, array $byXref): void
	{
		foreach ($record as $line) {
			self::assertLessThanOrEqual(255, mb_strlen($line), $line);
			if (preg_match('/^1 (FAMC|FAMS|HUSB|WIFE|CHIL|SOUR|SUBM) (@\w+@)$/', $line, $m) !== 1) {
				continue;
			}

			self::assertArrayHasKey($m[2], $byXref, $line);
			$back = ['FAMC' => 'CHIL', 'CHIL' => 'FAMC', 'HUSB' => 'FAMS', 'WIFE' => 'FAMS'][$m[1]] ?? null;
			if ($back !== null) {
				self::assertContains('1 ' . $back . ' ' . $this->xref($record), $byXref[$m[2]], $this->xref($record) . ' ' . $line);
			}
		}
	}

	/**
	 * $count lines of the record from $line on.
	 *
	 * @param list<string> $record
	 * @return list<string>
	 */
	private function after(array $record, string $line, int $count): array
	{
		$at = array_search($line, $record, true);
		self::assertIsInt($at, 'No ' . $line);

		return array_slice($record, $at, $count);
	}

	/** @param array<string, mixed>|null $data */
	private function export(?array $data = null): string
	{
		Fixture::removeDir($this->dir);
		$this->dir = Fixture::dataDir($data);
		$config = Fixture::config($this->dir);
		$repository = new Repository($config, new SiteDataLoader($config));
		$blocks = new GedcomBlocks($repository);
		$gedcom = new Gedcom($repository, new GedcomPeople($repository, $blocks), new GedcomFamilies($repository, $blocks));

		return $gedcom->export(new DateTimeImmutable('2026-11-15'));
	}

	/** @return list<non-empty-list<string>> the file split into level-0 records */
	private function records(string $file): array
	{
		$records = [];
		foreach (explode("\r\n", rtrim(substr($file, 3))) as $line) {
			$last = array_key_last($records);
			if (str_starts_with($line, '0 ') || $last === null) {
				$records[] = [$line];
			} else {
				$records[$last][] = $line;
			}
		}

		return $records;
	}

	/**
	 * @param list<non-empty-list<string>> $records
	 * @return non-empty-list<string>
	 */
	private function find(array $records, string $tag, string $line): array
	{
		foreach ($records as $record) {
			if (str_ends_with($record[0], ' ' . $tag) && in_array($line, $record, true)) {
				return $record;
			}
		}

		self::fail('No ' . $tag . ' with ' . $line);
	}

	/** @param list<non-empty-list<string>> $records */
	private function id(array $records, string $name): string
	{
		return trim($this->xref($this->find($records, 'INDI', '1 NAME ' . $name)), '@');
	}

	/** @param non-empty-list<string> $record */
	private function xref(array $record): string
	{
		return explode(' ', $record[0])[1];
	}
}
