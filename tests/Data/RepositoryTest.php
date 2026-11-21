<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Record;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\Fixture;

final class RepositoryTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testSiteSettings(): void
	{
		self::assertTrue($this->repository->isAvailable());
		self::assertSame('Rodokmen Testových', $this->repository->title());
		self::assertSame('Testovací patička', $this->repository->siteConfig()->footer);
		self::assertSame('antonin-novak-2024', $this->repository->rootSlug());
		self::assertSame(3, $this->repository->stats()->generations);
		self::assertSame($this->dir, $this->repository->dataDir());
	}

	public function testMissingDataStillGivesTitle(): void
	{
		unlink($this->dir . '/site.json');

		self::assertFalse($this->repository->isAvailable());
		self::assertSame('Rodokmen', $this->repository->title());
		self::assertSame('', $this->repository->siteConfig()->footer);
	}

	public function testPersonLookupFollowsAliases(): void
	{
		self::assertSame('Jan Novák', $this->repository->person('jan-novak-1990')?->name);
		self::assertSame('Jan Novák', $this->repository->person('stary-slug')?->name);
		self::assertNull($this->repository->person('nikdo'));
		self::assertNull($this->repository->person(''));
		self::assertNull($this->repository->person(null));
		self::assertCount(4, $this->repository->people());
	}

	public function testFamilies(): void
	{
		self::assertSame('antonin-novak-2024', $this->repository->family('jan-novak-1990-a-marie-mala-1992')?->child);
		self::assertNull($this->repository->family('nic'));
		self::assertNull($this->repository->family(null));
	}

	public function testRecords(): void
	{
		self::assertCount(1, $this->repository->records());
		self::assertSame('Křest Jana Nováka', $this->repository->record('praha_krest_jan_novak_1990')?->title);
		self::assertNull($this->repository->record('nic'));
		self::assertSame(
			['praha_krest_jan_novak_1990'],
			array_map(static fn (Record $r): string => $r->key, $this->repository->recordsByKeys(['nic', 'praha_krest_jan_novak_1990'])),
		);
	}

	public function testLines(): void
	{
		self::assertSame('Novákovi', $this->repository->line('novakovi')?->name);
		self::assertNull($this->repository->line('nic'));
		self::assertSame('novakovi', $this->repository->lineByNumber(8)?->slug);
		self::assertNull($this->repository->lineByNumber(null));
		self::assertSame('#336699', $this->repository->lineColor(8));
		self::assertSame('#9a8f7d', $this->repository->lineColor(99));
	}

	public function testPlaces(): void
	{
		self::assertCount(1, $this->repository->places());
		self::assertSame(['Praha'], $this->repository->placeNamesOf('josef-novak-1960'));
		self::assertSame(['Praha'], $this->repository->placeNamesOf('antonin-novak-2024'));
		self::assertSame([], $this->repository->placesOf('marie-mala-1992'));
	}

	public function testNotesAndLists(): void
	{
		self::assertStringContainsString('## Dva Augustinové', (string) $this->repository->markdown('zajimavosti'));
		self::assertNull($this->repository->markdown('denik'));
		self::assertNull($this->repository->markdown('../site'));
		self::assertNull($this->repository->markdown('pribeh-..'));
		self::assertStringContainsString('Příběh Malých', (string) $this->repository->markdown('pribeh-malovi'));
		file_put_contents($this->dir . '/md/pribeh-novy--rod-2.md', 'Text');
		self::assertSame('Text', $this->repository->markdown('pribeh-novy--rod-2'), 'any line slug rodokmen-sync accepts');
		self::assertCount(1, $this->repository->documents());
		self::assertCount(2, $this->repository->issues());
		self::assertCount(1, $this->repository->hypotheses());
	}

	public function testAncestorGenerations(): void
	{
		self::assertSame(
			[['antonin-novak-2024'], ['jan-novak-1990', 'marie-mala-1992'], ['josef-novak-1960', null, null, null]],
			$this->repository->ancestorGenerations('antonin-novak-2024', 5),
		);
		self::assertSame(
			[['antonin-novak-2024'], ['jan-novak-1990', 'marie-mala-1992']],
			$this->repository->ancestorGenerations('antonin-novak-2024', 1),
		);
		self::assertSame([['josef-novak-1960']], $this->repository->ancestorGenerations('josef-novak-1960', 3));
	}

	public function testAncestorCount(): void
	{
		self::assertSame(3, $this->repository->ancestorCount('antonin-novak-2024'));
		self::assertSame(1, $this->repository->ancestorCount('jan-novak-1990'));
		self::assertSame(0, $this->repository->ancestorCount('nikdo'));
	}

	public function testPathBetween(): void
	{
		self::assertSame(
			['josef-novak-1960', 'jan-novak-1990', 'antonin-novak-2024'],
			$this->repository->pathBetween('josef-novak-1960', 'antonin-novak-2024'),
		);
		self::assertSame(['josef-novak-1960', 'jan-novak-1990'], $this->repository->pathBetween('josef-novak-1960', 'jan-novak-1990'));
		self::assertNull($this->repository->pathBetween('marie-mala-1992', 'jan-novak-1990'));
		self::assertNull($this->repository->pathBetween('nikdo', 'jan-novak-1990'));
	}
}
