<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Image;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Config;
use Rodokmen\Site\Image\ImageSize;
use Rodokmen\Site\Image\Thumbnails;
use Rodokmen\Site\Image\WebpResizer;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\Tests\Support\Images;
use const IMAGETYPE_WEBP;

final class ThumbnailsTest extends TestCase
{
	private string $dir;

	private Config $config;

	private Thumbnails $thumbnails;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$this->config = Fixture::config($this->dir);
		$this->thumbnails = new Thumbnails($this->config, new WebpResizer());
		if (!$this->thumbnails->isSupported()) {
			self::markTestSkipped('GD cannot write WebP here');
		}
		Images::jpeg($this->dir . '/img/krest.jpg', 1800, 1200);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testMakesAWebpOfTheWidth(): void
	{
		$path = $this->thumbnails->thumbnail('krest', 960);

		self::assertNotNull($path);
		self::assertStringStartsWith($this->dir . '/cache/thumbs/960/krest-', $path);
		$size = getimagesize($path);
		self::assertNotFalse($size);
		self::assertSame([960, 640, IMAGETYPE_WEBP], [$size[0], $size[1], $size[2]]);
	}

	public function testNeverScalesUp(): void
	{
		$path = $this->thumbnails->thumbnail('krest', 1440);
		self::assertNotNull($path);
		self::assertSame(1440, getimagesize($path)[0] ?? null);

		Images::jpeg($this->dir . '/img/maly.jpg', 600, 300);
		$small = $this->thumbnails->thumbnail('maly', 960);
		self::assertNotNull($small);
		self::assertSame(600, getimagesize($small)[0] ?? null);
	}

	public function testReusesTheCachedCopy(): void
	{
		$path = $this->thumbnails->thumbnail('krest', 480);
		self::assertNotNull($path);
		touch($path, time() - 3600);
		clearstatcache();

		self::assertSame($path, $this->thumbnails->thumbnail('krest', 480));
		self::assertSame(time() - 3600, filemtime($path));
	}

	public function testAChangedSourceGetsANewCopyAndTheOldOneGoes(): void
	{
		$old = $this->thumbnails->thumbnail('krest', 480);
		self::assertNotNull($old);

		Images::jpeg($this->dir . '/img/krest.jpg', 900, 900);
		touch($this->dir . '/img/krest.jpg', time() + 10);
		clearstatcache();
		$new = $this->thumbnails->thumbnail('krest', 480);

		self::assertNotNull($new);
		self::assertNotSame($old, $new);
		$size = getimagesize($new);
		self::assertNotFalse($size);
		self::assertSame([480, 480], [$size[0], $size[1]]);
		self::assertFileDoesNotExist($old);
	}

	public function testGivesNothingItCannotMake(): void
	{
		file_put_contents($this->dir . '/img/rozbity.jpg', 'not a jpeg');

		self::assertNull($this->thumbnails->thumbnail('krest', 500), 'only the fixed widths');
		self::assertNull($this->thumbnails->thumbnail('chybi', 480), 'no source');
		self::assertNull($this->thumbnails->thumbnail('rozbity', 480), 'broken source');
		self::assertNull(new Thumbnails($this->config, new WebpResizer(), false)->thumbnail('krest', 480), 'no WebP support');
		self::assertDirectoryDoesNotExist($this->dir . '/cache/thumbs/500');
	}

	public function testDoesNotDecodeHugeImages(): void
	{
		$target = $this->dir . '/cache/huge.webp';

		self::assertFalse(new WebpResizer()->resize($this->dir . '/img/krest.jpg', new ImageSize(8000, 6000), 480, $target));
		self::assertFileDoesNotExist($target);
	}

	public function testSourceSize(): void
	{
		self::assertEquals(new ImageSize(1800, 1200), $this->thumbnails->sourceSize('krest'));
		self::assertNull($this->thumbnails->sourceSize('chybi'));
	}

	public function testDoesNotDecodeMoreThanTheMemoryLimitAllows(): void
	{
		$limit = ini_get('memory_limit');
		ini_set('memory_limit', (string) (memory_get_usage() + 50 * 1024 * 1024));

		try {
			$resizer = new WebpResizer();
			self::assertTrue($resizer->canResize(new ImageSize(1800, 1200)));
			self::assertFalse($resizer->canResize(new ImageSize(5000, 4000)), '20 MP needs more than 50 MB');
			self::assertEquals(new ImageSize(1800, 1200), $this->thumbnails->resizableSize('krest'));
		} finally {
			ini_set('memory_limit', $limit);
		}
	}

	public function testPruneRemovesCopiesOfChangedAndRemovedSources(): void
	{
		Images::jpeg($this->dir . '/img/foto.jpg', 1000, 800);
		$kept = $this->thumbnails->thumbnail('krest', 480);
		$removed = $this->thumbnails->thumbnail('foto', 960);
		$changed = $this->thumbnails->thumbnail('foto', 480);
		self::assertNotNull($kept);
		self::assertNotNull($removed);
		self::assertNotNull($changed);
		unlink($this->dir . '/img/foto.jpg');

		$dir = $this->dir . '/cache/thumbs/480';
		file_put_contents($dir . '/.old.tmp', 'x');
		touch($dir . '/.old.tmp', time() - 3600);
		file_put_contents($dir . '/.fresh.tmp', 'x');
		file_put_contents($dir . '/README', 'not a thumbnail');

		self::assertSame(3, $this->thumbnails->prune());
		self::assertFileExists($kept);
		self::assertFileDoesNotExist($removed);
		self::assertFileDoesNotExist($changed);
		self::assertFileDoesNotExist($dir . '/.old.tmp');
		self::assertFileExists($dir . '/.fresh.tmp', 'a request may still be writing it');
		self::assertFileExists($dir . '/README');
		self::assertSame(0, $this->thumbnails->prune());
	}
}
