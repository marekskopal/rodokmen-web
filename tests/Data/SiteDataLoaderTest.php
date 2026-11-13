<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\Fixture;
use RuntimeException;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class SiteDataLoaderTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testLoadsAndCachesTheExport(): void
	{
		$loader = new SiteDataLoader(Fixture::config($this->dir));

		self::assertSame('antonin-novak-2024', $loader->load()->root);

		$cache = glob($this->dir . '/cache/site-*.php');
		self::assertIsArray($cache);
		self::assertCount(1, $cache);

		// The cache is used from now on: the JSON (same size and time) is no longer read.
		$json = $this->dir . '/site.json';
		$mtime = (int) filemtime($json);
		file_put_contents($json, str_repeat(' ', (int) filesize($json)));
		touch($json, $mtime);
		self::assertSame('antonin-novak-2024', $loader->load()->root);
	}

	public function testNewExportReplacesOldCache(): void
	{
		$loader = new SiteDataLoader(Fixture::config($this->dir));
		$loader->load();

		$data = Fixture::siteData();
		$data['root'] = 'jan-novak-1990';
		file_put_contents($this->dir . '/site.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
		touch($this->dir . '/site.json', time() + 10);

		self::assertSame('jan-novak-1990', $loader->load()->root);
		$cache = glob($this->dir . '/cache/site-*.php');
		self::assertIsArray($cache);
		self::assertCount(1, $cache);
	}

	public function testMissingDataThrows(): void
	{
		unlink($this->dir . '/site.json');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/' . preg_quote('bin/rodokmen-sync', '/') . '/');

		new SiteDataLoader(Fixture::config($this->dir))->load();
	}
}
