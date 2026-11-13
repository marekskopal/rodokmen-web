<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Container;
use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Tests\Support\Fixture;
use RuntimeException;

final class ContainerTest extends TestCase
{
	public function testAutowiresSharedInstances(): void
	{
		$container = new Container();
		$config = Fixture::config(sys_get_temp_dir());
		$container->set(Config::class, $config);

		$home = $container->service(HomePerson::class);

		self::assertSame($home, $container->get(HomePerson::class));
		self::assertSame($container->service(Repository::class), $container->service(Repository::class));
		self::assertSame($config, $container->get(Config::class));
		self::assertTrue($container->has(Repository::class));
		self::assertFalse($container->has('Nope\\Missing'));
	}

	public function testUnknownServiceThrowsNotFound(): void
	{
		$this->expectException(NotFoundExceptionInterface::class);

		new Container()->get('Nope\\Missing');
	}

	public function testUnresolvableParameterThrows(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/' . preg_quote('Cannot autowire parameter $rootDir of Rodokmen\\Site\\Config', '/') . '/');

		new Container()->get(Config::class);
	}
}
