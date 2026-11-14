<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Cache;

use DateInterval;
use MarekSkopal\Router\Attribute\RoutePost;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Cache\InvalidCacheKeyException;
use Rodokmen\Site\Cache\PhpFileCache;
use Rodokmen\Site\Tests\Support\Fixture;

final class PhpFileCacheTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/rodokmen-cache-' . bin2hex(random_bytes(6)) . '/routes';
	}

	protected function tearDown(): void
	{
		Fixture::removeDir(dirname($this->dir));
	}

	public function testStoresObjectsInPhpFiles(): void
	{
		$cache = new PhpFileCache($this->dir, 'v1');
		$routes = ['A::b' => new RoutePost('/api/x')];

		self::assertNull($cache->get('routes'));
		self::assertTrue($cache->set('routes', $routes));

		self::assertEquals($routes, (new PhpFileCache($this->dir, 'v1'))->get('routes'));
		self::assertTrue($cache->has('routes'));
		self::assertFileExists($this->dir . '/routes.php');
		// Stored false and null are values, not misses.
		$cache->set('false', false);
		self::assertFalse($cache->get('false', 'default'));
	}

	public function testEntryOfAnotherVersionIsMissing(): void
	{
		(new PhpFileCache($this->dir, 'v1'))->set('routes', ['old']);
		$cache = new PhpFileCache($this->dir, 'v2');

		self::assertSame('default', $cache->get('routes', 'default'));
		self::assertFalse($cache->has('routes'));

		$cache->set('routes', ['new']);
		self::assertSame(['new'], $cache->get('routes'));
		self::assertNull((new PhpFileCache($this->dir, 'v1'))->get('routes'));
	}

	public function testExpiredEntryIsMissing(): void
	{
		$cache = new PhpFileCache($this->dir);
		$cache->set('past', 'x', -1);
		$cache->set('soon', 'x', 3600);
		$cache->set('interval', 'x', new DateInterval('PT1H'));

		self::assertFalse($cache->has('past'));
		self::assertTrue($cache->has('soon'));
		self::assertTrue($cache->has('interval'));
	}

	public function testBrokenFileIsMissing(): void
	{
		mkdir($this->dir, 0777, true);
		file_put_contents($this->dir . '/routes.php', '<?php return "garbage";');

		self::assertNull((new PhpFileCache($this->dir))->get('routes'));
	}

	public function testUnreadableValueIsMissing(): void
	{
		mkdir($this->dir, 0777, true);
		$entry = ['version' => '', 'expires' => null, 'value' => 'a:1:{broken'];
		file_put_contents($this->dir . '/routes.php', '<?php return ' . var_export($entry, true) . ';');

		self::assertNull((new PhpFileCache($this->dir))->get('routes'));
	}

	public function testDeleteClearAndMultiple(): void
	{
		$cache = new PhpFileCache($this->dir);
		self::assertTrue($cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]));
		self::assertSame(['a' => 1, 'b' => 2, 'x' => null], $cache->getMultiple(['a', 'b', 'x']));

		self::assertTrue($cache->delete('a'));
		self::assertTrue($cache->delete('a'));
		self::assertTrue($cache->deleteMultiple(['b']));
		self::assertSame(['a' => 0, 'b' => 0, 'c' => 3], $cache->getMultiple(['a', 'b', 'c'], 0));

		self::assertTrue($cache->clear());
		self::assertFalse($cache->has('c'));
	}

	public function testRejectsKeysThatAreNotFileNames(): void
	{
		$this->expectException(InvalidCacheKeyException::class);

		(new PhpFileCache($this->dir))->get('../routes');
	}
}
