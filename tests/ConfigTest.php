<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Config;
use Rodokmen\Site\Tests\Support\Fixture;

#[BackupGlobals(false)]
final class ConfigTest extends TestCase
{
	private string $dir;

	/** @var array<string, string|false> */
	private array $saved = [];

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		foreach (['SITE_DATA_DIR', 'SITE_CACHE_DIR', 'SITE_DEFAULT_PERSON', 'SITE_TRUST_PROXY', 'SITE_LOGIN_DAYS', 'SITE_FORCE_HTTPS', 'SITE_TEST_ONLY'] as $name) {
			$this->saved[$name] = getenv($name);
			putenv($name);
		}
	}

	protected function tearDown(): void
	{
		foreach ($this->saved as $name => $value) {
			putenv($value === false ? $name : $name . '=' . $value);
		}
		Fixture::removeDir($this->dir);
	}

	public function testDefaults(): void
	{
		$config = Config::fromEnvironment($this->dir);

		self::assertSame($this->dir . '/var/data', $config->dataDir);
		self::assertSame($this->dir . '/var/cache', $config->cacheDir);
		self::assertNull($config->defaultPerson);
		self::assertFalse($config->trustProxy);
		self::assertSame(30, $config->loginDays);
		self::assertTrue($config->forceHttps);
	}

	public function testEnvironmentVariables(): void
	{
		putenv('SITE_DATA_DIR=/data/');
		putenv('SITE_DEFAULT_PERSON= jan ');
		putenv('SITE_TRUST_PROXY=1');
		putenv('SITE_LOGIN_DAYS=0');
		putenv('SITE_FORCE_HTTPS=0');

		$config = Config::fromEnvironment($this->dir);

		self::assertSame('/data', $config->dataDir);
		self::assertSame('jan', $config->defaultPerson);
		self::assertTrue($config->trustProxy);
		self::assertSame(1, $config->loginDays);
		self::assertFalse($config->forceHttps);
	}

	public function testDotEnvFillsOnlyMissingVariables(): void
	{
		putenv('SITE_TRUST_PROXY=0');
		file_put_contents(
			$this->dir . '/.env',
			"# comment\nSITE_TEST_ONLY='z env'\nSITE_TRUST_PROXY=1\nSITE_DEFAULT_PERSON=\"josef\"\nbroken line\n",
		);

		$config = Config::fromEnvironment($this->dir);

		self::assertSame('z env', getenv('SITE_TEST_ONLY'));
		self::assertFalse($config->trustProxy, 'real environment wins over .env');
		self::assertSame('josef', $config->defaultPerson);
	}
}
