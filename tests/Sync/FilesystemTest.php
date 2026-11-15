<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Sync;

use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Sync\Filesystem;
use Rodokmen\Site\Sync\SyncException;
use Rodokmen\Site\Tests\Support\Fixture;
use RuntimeException;

final class FilesystemTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/rodokmen-fs-' . bin2hex(random_bytes(6));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testCopyKeepsTheModificationTime(): void
	{
		file_put_contents($this->dir . '/a', 'crop');
		touch($this->dir . '/a', 1700000000);

		new Filesystem()->copyKeepingTime($this->dir . '/a', $this->dir . '/b');

		clearstatcache();
		self::assertSame('crop', file_get_contents($this->dir . '/b'));
		self::assertSame(1700000000, filemtime($this->dir . '/b'));

		$this->expectException(RuntimeException::class);
		new Filesystem()->copyKeepingTime($this->dir . '/missing', $this->dir . '/c');
	}

	public function testWriteStreamReturnsTheHash(): void
	{
		$hash = new Filesystem()->writeStream((new StreamFactory())->createStream('hello world'), $this->dir . '/f', 100, 4);

		self::assertSame(hash('sha256', 'hello world'), $hash);
		self::assertSame('hello world', file_get_contents($this->dir . '/f'));
	}

	public function testWriteStreamStopsAtTheSizeLimit(): void
	{
		try {
			new Filesystem()->writeStream((new StreamFactory())->createStream('0123456789'), $this->dir . '/f', 9, 4);
			self::fail('Expected a SyncException');
		} catch (SyncException $e) {
			self::assertSame(413, $e->status);
		}
	}
}
