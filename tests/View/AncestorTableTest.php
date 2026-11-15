<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\AncestorEntry;
use Rodokmen\Site\View\AncestorGeneration;
use Rodokmen\Site\View\AncestorTable;

final class AncestorTableTest extends TestCase
{
	private string $dir = '';

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testNumbersCountFromTheChosenPerson(): void
	{
		[$table, $repository] = $this->table();
		$generations = $table->build($this->person($repository, 'antonin-novak-2024'), 10);

		self::assertSame([0, 1, 2], array_map(static fn (AncestorGeneration $g): int => $g->level, $generations));
		self::assertSame(
			['Osoba', 'Rodiče', 'Prarodiče'],
			array_map(static fn (AncestorGeneration $g): string => $g->title(), $generations),
		);
		// Jan Novák's mother is unknown: number 5 is missing.
		self::assertSame(
			[1 => 'antonin-novak-2024', 2 => 'jan-novak-1990', 3 => 'marie-mala-1992', 4 => 'josef-novak-1960'],
			$this->numbers($generations),
		);
		self::assertSame(3, $table->availableDepth($this->person($repository, 'antonin-novak-2024')) + 1);
	}

	public function testNumbersStartAgainFromAnotherPerson(): void
	{
		[$table, $repository] = $this->table();

		self::assertSame(
			[1 => 'jan-novak-1990', 2 => 'josef-novak-1960'],
			$this->numbers($table->build($this->person($repository, 'jan-novak-1990'), 10)),
		);
	}

	public function testGenerationLimit(): void
	{
		[$table, $repository] = $this->table();
		$person = $this->person($repository, 'antonin-novak-2024');

		self::assertSame(
			[1 => 'antonin-novak-2024', 2 => 'jan-novak-1990', 3 => 'marie-mala-1992'],
			$this->numbers($table->build($person, 1)),
		);
		self::assertSame([1 => 'antonin-novak-2024'], $this->numbers($table->build($person, 0)));
		self::assertSame([1 => 'antonin-novak-2024'], $this->numbers($table->build($person, -3)));
	}

	public function testAPersonWithoutAncestorsIsListedAlone(): void
	{
		[$table, $repository] = $this->table();
		$josef = $this->person($repository, 'josef-novak-1960');

		self::assertSame([1 => 'josef-novak-1960'], $this->numbers($table->build($josef, 5)));
		self::assertSame(0, $table->availableDepth($josef));
	}

	public function testAnAncestorListedTwicePointsToTheFirstNumber(): void
	{
		$data = Fixture::siteData();
		self::assertIsArray($data['people']);
		self::assertIsArray($data['people']['marie-mala-1992']);
		$data['people']['marie-mala-1992']['father'] = 'josef-novak-1960';
		[$table, $repository] = $this->table($data);

		$grandparents = $table->build($this->person($repository, 'antonin-novak-2024'), 2)[2]->entries;

		self::assertEquals(
			[
				new AncestorEntry(4, $this->person($repository, 'josef-novak-1960')),
				new AncestorEntry(6, $this->person($repository, 'josef-novak-1960'), 4),
			],
			$grandparents,
		);
		self::assertSame([8, 9], [$grandparents[0]->fatherNumber(), $grandparents[0]->motherNumber()]);
	}

	public function testTheAncestorsOfARepeatedAncestorAreNotRepeatedAndACycleEnds(): void
	{
		$data = Fixture::siteData();
		self::assertIsArray($data['people']);
		self::assertIsArray($data['people']['marie-mala-1992']);
		self::assertIsArray($data['people']['josef-novak-1960']);
		$data['people']['marie-mala-1992']['father'] = 'josef-novak-1960';
		// A cycle in the data
		$data['people']['josef-novak-1960']['father'] = 'jan-novak-1990';
		[$table, $repository] = $this->table($data);
		$antonin = $this->person($repository, 'antonin-novak-2024');

		self::assertSame(
			[
				1 => 'antonin-novak-2024',
				2 => 'jan-novak-1990',
				3 => 'marie-mala-1992',
				4 => 'josef-novak-1960',
				6 => 'josef-novak-1960',
				8 => 'jan-novak-1990',
			],
			$this->numbers($table->build($antonin, AncestorTable::MaxDepth)),
		);
		self::assertSame(3, $table->availableDepth($antonin));
	}

	/**
	 * @param array<string, mixed>|null $data
	 * @return array{AncestorTable, Repository}
	 */
	private function table(?array $data = null): array
	{
		$this->dir = Fixture::dataDir($data);
		$config = Fixture::config($this->dir);
		$repository = new Repository($config, new SiteDataLoader($config));

		return [new AncestorTable($repository), $repository];
	}

	private function person(Repository $repository, string $slug): Person
	{
		$person = $repository->person($slug);
		self::assertNotNull($person);

		return $person;
	}

	/**
	 * @param list<AncestorGeneration> $generations
	 * @return array<int, string> number => slug
	 */
	private function numbers(array $generations): array
	{
		$numbers = [];
		foreach ($generations as $generation) {
			foreach ($generation->entries as $entry) {
				$numbers[$entry->number] = $entry->person->slug;
			}
		}

		return $numbers;
	}
}
