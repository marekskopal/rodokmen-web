<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\Fixture;

final class HomePersonTest extends TestCase
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

	public function testDefaultsToTreeRoot(): void
	{
		$home = $this->home(null);

		self::assertSame('antonin-novak-2024', $home->defaultSlug());
		self::assertSame('antonin-novak-2024', $home->slug(new ServerRequest()));
		self::assertFalse($home->isCustom(new ServerRequest()));
	}

	public function testConfiguredDefaultPerson(): void
	{
		self::assertSame('jan-novak-1990', $this->home('jan-novak-1990')->defaultSlug());
		self::assertSame('antonin-novak-2024', $this->home('nikdo')->defaultSlug());
	}

	public function testCookieChoice(): void
	{
		$home = $this->home(null);
		$request = new ServerRequest()->withCookieParams([HomePerson::CookieName => 'josef-novak-1960']);

		self::assertSame('josef-novak-1960', $home->slug($request));
		self::assertTrue($home->isCustom($request));
		self::assertSame('antonin-novak-2024', $home->slug(new ServerRequest()->withCookieParams([HomePerson::CookieName => 'nikdo'])));
	}

	private function home(?string $defaultPerson): HomePerson
	{
		$config = Fixture::config($this->dir, $defaultPerson);

		return new HomePerson(new Repository($config, new SiteDataLoader($config)), $config);
	}
}
