<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\Poster\PosterHeading;

final class PosterHeadingTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private PosterHeading $heading;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
		$this->heading = new PosterHeading($this->repository, new HomePerson($this->repository, $config));
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	/** @return iterable<string, array{string, string}> */
	public static function surnames(): iterable
	{
		yield 'consonant' => ['Novák', 'Novákových'];
		yield 'female -ová' => ['Nováková', 'Novákových'];
		yield '-a' => ['Rybka', 'Rybkových'];
		yield 'female of -a' => ['Rybková', 'Rybkových'];
		yield '-o' => ['Šidlo', 'Šidlových'];
		yield '-e' => ['Kubice', 'Kubicových'];
		yield '-ě softens' => ['Purkyně', 'Purkyňových'];
		yield 'adjective' => ['Veselý', 'Veselých'];
		yield 'female adjective' => ['Malá', 'Malých'];
		yield 'soft adjective' => ['Krejčí', 'Krejčích'];
		yield '-ek loses e' => ['Hájek', 'Hájkových'];
		yield '-ek after a syllabic consonant' => ['Vlček', 'Vlčkových'];
		yield '-ec loses e' => ['Němec', 'Němcových'];
		yield '-ec after two consonants keeps e' => ['Kadlec', 'Kadlecových'];
		yield 'short -ek keeps e' => ['Rek', 'Rekových'];
		yield '-ek after an initial cluster keeps e' => ['Šnek', 'Šnekových'];
		yield '-něk softens' => ['Vaněk', 'Vaňkových'];
		yield 'female of -něk' => ['Vaňková', 'Vaňkových'];
		yield '-věk keeps ě' => ['Člověk', 'Člověkových'];
		yield 'capital -ek' => ['HÁJEK', 'HÁJKových'];
		yield 'abbreviated' => ['N.', ''];
		yield 'not a surname' => ['ml.', ''];
		yield 'double surname' => ['Nováková-Malá', ''];
		yield '-el keeps e' => ['Chmel', 'Chmelových'];
		yield 'already of the family' => ['Janů', 'Janů'];
		yield 'capitals' => ['NOVÁKOVÁ', 'NOVÁKových'];
		yield 'spaces' => [' Skopal ', 'Skopalových'];
		yield 'no surname' => ['', ''];
		yield 'one letter' => ['N', ''];
	}

	#[DataProvider('surnames')]
	public function testFamilyOfASurname(string $surname, string $family): void
	{
		self::assertSame($family, $this->heading->family($surname));
	}

	public function testTheSitesPersonKeepsTheSiteTitle(): void
	{
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($root);

		self::assertSame('Rodokmen Testových', $this->heading->of($root));
	}

	public function testTheConfiguredDefaultPersonKeepsTheSiteTitleToo(): void
	{
		$config = Fixture::config($this->dir, defaultPerson: 'jan-novak-1990');
		$heading = new PosterHeading($this->repository, new HomePerson($this->repository, $config));
		$father = $this->repository->person('jan-novak-1990');
		$root = $this->repository->person('antonin-novak-2024');
		self::assertNotNull($father);
		self::assertNotNull($root);

		self::assertSame('Rodokmen Testových', $heading->of($father));
		self::assertSame('Rodokmen Testových', $heading->of($root));
	}

	public function testAnybodyElseGetsTheirFamily(): void
	{
		$father = $this->repository->person('jan-novak-1990');
		$mother = $this->repository->person('marie-mala-1992');
		self::assertNotNull($father);
		self::assertNotNull($mother);

		self::assertSame('Rodokmen Novákových', $this->heading->of($father));
		self::assertSame('Rodokmen Malých', $this->heading->of($mother));
	}
}
