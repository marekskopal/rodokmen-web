<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Data\Stories;
use Rodokmen\Site\Tests\Support\Fixture;

final class StoriesTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private Stories $stories;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
		$this->stories = new Stories($this->repository);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testMainStory(): void
	{
		$story = $this->stories->main();

		self::assertNotNull($story);
		self::assertSame('Příběh Nováků', $story->title);
		self::assertSame('Od Josefa k Antonínovi – tři generace v Praze.', $story->lead);
		self::assertStringStartsWith('## Josef v Praze (1960)', $story->text);
		self::assertSame('novakovi', $story->line?->slug);
		self::assertSame('Příběh Nováků', $this->repository->siteConfig()->story->menu);
	}

	public function testStoryWithoutFrontMatterTakesItsTitleFromTheHeading(): void
	{
		file_put_contents($this->dir . '/md/pribeh.md', "# Příběh rodu Nováků\n\nText.\n");

		$story = $this->stories->main();

		self::assertSame('Příběh rodu Nováků', $story?->title);
		self::assertSame('', $story->lead);
		self::assertSame('Text.', trim($story->text));

		file_put_contents($this->dir . '/md/pribeh.md', "# Příběh rodu Nováků ##\n\nText.\n");
		self::assertSame('Příběh rodu Nováků', $this->stories->main()?->title, 'closing hashes of the heading');
	}

	public function testNullLineMeansTheDefaultOne(): void
	{
		$data = Fixture::siteData();
		$config = $data['config'];
		self::assertIsArray($config);
		$data['config'] = ['pribeh' => ['rod' => null]] + $config;
		$dir = Fixture::dataDir($data);
		$siteConfig = Fixture::config($dir);

		try {
			self::assertSame(
				'novakovi',
				new Stories(new Repository($siteConfig, new SiteDataLoader($siteConfig)))->main()?->line?->slug,
			);
		} finally {
			Fixture::removeDir($dir);
		}
	}

	public function testNoStory(): void
	{
		$data = Fixture::siteData();
		$config = $data['config'];
		self::assertIsArray($config);
		$data['config'] = ['maPribeh' => false] + $config;
		$dir = Fixture::dataDir($data);
		file_put_contents($dir . '/md/pribeh.md', "---\ntitle: Příběh\nlead: ''\n---\n");
		$siteConfig = Fixture::config($dir);

		try {
			self::assertNull(
				new Stories(new Repository($siteConfig, new SiteDataLoader($siteConfig)))->main(),
				'the exporter\'s placeholder',
			);
		} finally {
			Fixture::removeDir($dir);
		}
	}

	public function testStoryOfAnOldExport(): void
	{
		$data = Fixture::siteData();
		$config = $data['config'];
		self::assertIsArray($config);
		unset($config['pribeh'], $config['maPribeh']);
		$data['config'] = $config;
		$dir = Fixture::dataDir($data);
		$siteConfig = Fixture::config($dir);
		$stories = new Stories(new Repository($siteConfig, new SiteDataLoader($siteConfig)));

		try {
			self::assertSame('Příběh Nováků', $stories->main()?->title, 'the file decides');
			self::assertSame('novakovi', $stories->main()->line?->slug, 'line 8 by default');
			unlink($dir . '/md/pribeh.md');
			self::assertNull($stories->main());
		} finally {
			Fixture::removeDir($dir);
		}
	}
}
