<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\ArrayReader;
use Rodokmen\Site\Data\InvalidDataException;
use Rodokmen\Site\Data\Model\Box;
use Rodokmen\Site\Data\Model\Issue;
use Rodokmen\Site\Data\Model\Line;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Model\Place;
use Rodokmen\Site\Data\Model\Record;
use Rodokmen\Site\Data\Model\Side;
use Rodokmen\Site\Data\Model\SiteData;
use Rodokmen\Site\Tests\Support\Fixture;

final class ModelTest extends TestCase
{
	private SiteData $data;

	protected function setUp(): void
	{
		$this->data = SiteData::fromReader(ArrayReader::of(Fixture::siteData()));
	}

	public function testSiteDataHydratesEverything(): void
	{
		self::assertSame('Rodokmen Testových', $this->data->config->title);
		self::assertSame('Testovací patička', $this->data->config->footer);
		self::assertSame('Markovi Testovi', $this->data->config->contactName);
		self::assertSame('test@example.org', $this->data->config->contactEmail);
		self::assertSame('antonin-novak-2024', $this->data->root);
		self::assertSame(['stary-slug' => 'jan-novak-1990'], $this->data->aliases);
		self::assertCount(4, $this->data->people);
		self::assertCount(2, $this->data->families);
		self::assertArrayHasKey('praha_krest_jan_novak_1990', $this->data->records);
		self::assertSame(3, $this->data->stats->ancestors);
		self::assertSame('Odkud byl Josef', $this->data->hypotheses[0]->title);
		self::assertSame('kandidát', $this->data->hypotheses[0]->items[0]->text);
		self::assertSame(['josef-novak-1960'], $this->data->documents[0]->people);
	}

	public function testPerson(): void
	{
		$jan = $this->person('jan-novak-1990');

		self::assertSame(2, $jan->k);
		self::assertSame(8, $jan->line);
		self::assertSame('josef-novak-1960', $jan->father);
		self::assertNull($jan->mother);
		self::assertFalse($jan->isFemale());
		self::assertTrue($this->person('marie-mala-1992')->isFemale());
		self::assertSame(1990, $jan->birthYear());
		self::assertNull($this->person('antonin-novak-2024')->line);
	}

	#[DataProvider('shortRelations')]
	public function testShortRelDropsTheChainOfPra(string $rel, string $expected): void
	{
		$person = Person::fromReader(ArrayReader::of(['k' => 64, 'slug' => 'jan-novak-1790', 'name' => 'Jan Novák', 'rel' => $rel]));

		self::assertSame($expected, $person->shortRel());
	}

	/** @return iterable<string, array{string, string}> */
	public static function shortRelations(): iterable
	{
		yield 'ancestor of the 6th generation' => ['předek v 6. generaci (prapraprapradědeček)', 'předek v 6. generaci'];
		yield 'ancestress' => ['předkyně v 8. generaci (praprapraprapraprababička)', 'předkyně v 8. generaci'];
		yield 'near generations stay' => ['prapraprababička', 'prapraprababička'];
		yield 'other brackets stay' => ['otec (nevlastní)', 'otec (nevlastní)'];
		yield 'empty' => ['', ''];
	}

	public function testPersonSplitsSiblingsFromOtherRelatives(): void
	{
		$jan = $this->person('jan-novak-1990');

		self::assertSame(
			['Petr Novák (* 1993)', 'Eva Nová (* 1985)'],
			array_map(static fn (Side $s): string => $s->name, $jan->siblings()),
		);
		self::assertSame(['Karel Novák'], array_map(static fn (Side $s): string => $s->name, $jan->otherRelatives()));
	}

	public function testSide(): void
	{
		[$full, $half, $cousin] = $this->person('jan-novak-1990')->side;

		self::assertTrue($full->isSibling());
		self::assertTrue($full->isFullSibling());
		self::assertFalse($full->isHalfSibling());
		self::assertTrue($half->isSibling());
		self::assertFalse($half->isFullSibling());
		self::assertTrue($half->isHalfSibling());
		self::assertFalse($cousin->isSibling());
		self::assertSame('1993', $full->birthYear);
	}

	public function testSideDefaultsForOldExports(): void
	{
		$side = Side::fromReader(ArrayReader::of(['name' => 'Někdo', 't' => 'ignored']));

		self::assertSame('', $side->text);
		self::assertSame('', $side->rel);
		self::assertSame('', $side->birthYear);
		self::assertFalse($side->unsure);
		self::assertFalse($side->isSibling());
	}

	public function testFamilyOtherChildrenAreFullSiblingsOnly(): void
	{
		$family = $this->data->families['josef-novak-1960-rodina'];

		self::assertSame(['Petr Novák (* 1993)'], array_map(static fn (Side $s): string => $s->name, $family->otherChildren()));
		self::assertNull($family->mother);
	}

	public function testLineGenerationCount(): void
	{
		$line = $this->data->lines['novakovi'];

		self::assertSame(2, $line->generationCount());
		self::assertSame(0, new Line(1, 'x', 'X', '', '#000', 'x', [], [])->generationCount());
	}

	public function testRecord(): void
	{
		$record = $this->data->records['praha_krest_jan_novak_1990'];

		self::assertSame(1990, $record->year());
		self::assertEquals([new Box(0.1, 0.2, 0.9, 0.5)], $record->boxes);
		self::assertSame(12, $record->scan);

		$undated = Record::fromReader(ArrayReader::of(['key' => 'k', 'kind' => 'J', 'title' => 'Bez data']));
		self::assertSame(9999, $undated->year());
		self::assertSame([], $undated->boxes);
	}

	public function testBoxNeedsFourNumbers(): void
	{
		$this->expectException(InvalidDataException::class);

		Box::fromList([0.1, 0.2]);
	}

	public function testPlace(): void
	{
		$place = $this->data->places[0];

		self::assertSame('Praha', $place->name);
		self::assertSame([1960, 2024], $place->years);
		self::assertSame(0, $place->lines[1]->k);

		$noYears = Place::fromReader(ArrayReader::of(['name' => 'X', 'lat' => 1, 'lon' => 2, 'years' => null]));
		self::assertSame([], $noYears->years);
		self::assertSame(1.0, $noYears->lat);
	}

	public function testIssueSolved(): void
	{
		self::assertFalse(new Issue('a', 'Nevíme.')->isSolved());
		self::assertTrue(new Issue('a', 'Vyřešeno: ano.')->isSolved());
	}

	public function testInvalidPersonFails(): void
	{
		$data = Fixture::siteData();
		$data['people'] = ['x' => ['slug' => 'x', 'name' => 'X']];

		$this->expectException(InvalidDataException::class);
		$this->expectExceptionMessageMatches('/' . preg_quote('$.people.x.k: missing', '/') . '/');

		SiteData::fromReader(ArrayReader::of($data));
	}

	private function person(string $slug): Person
	{
		$person = $this->data->people[$slug] ?? null;
		self::assertNotNull($person);

		return $person;
	}
}
