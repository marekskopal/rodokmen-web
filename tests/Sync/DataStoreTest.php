<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Sync;

use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Sync\DataStore;
use Rodokmen\Site\Sync\Filesystem;
use Rodokmen\Site\Sync\Manifest;
use Rodokmen\Site\Sync\SyncException;
use Rodokmen\Site\Tests\Support\Fixture;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function is_link;
use function mkdir;
use function random_bytes;
use function readlink;
use function scandir;
use function sys_get_temp_dir;

final class DataStoreTest extends TestCase
{
	private string $root;

	private string $data;

	private DataStore $store;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/rodokmen-sync-' . bin2hex(random_bytes(6));
		$this->data = $this->root . '/data';
		mkdir($this->data . '/img', 0777, true);
		file_put_contents($this->data . '/site.json', 'old data');
		file_put_contents($this->data . '/img/same.jpg', 'same crop');
		$this->store = new DataStore(Fixture::config($this->data), new Filesystem());
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->root);
	}

	public function testMissingSkipsUnchangedAndUploadedFiles(): void
	{
		$manifest = Manifest::fromJson(
			ManifestTest::json(['site.json' => 'new data', 'img/same.jpg' => 'same crop', 'img/new.jpg' => 'new crop']),
		);

		self::assertEqualsCanonicalizing([hash('sha256', 'new data'), hash('sha256', 'new crop')], $this->store->missing($manifest));

		$this->upload('new crop');
		self::assertSame([hash('sha256', 'new data')], $this->store->missing($manifest));
	}

	public function testUploadRejectsContentNotMatchingItsHash(): void
	{
		try {
			$this->store->upload(hash('sha256', 'expected'), (new StreamFactory())->createStream('something else'));
			self::fail('Expected a SyncException');
		} catch (SyncException $e) {
			self::assertSame(422, $e->status);
		}

		self::assertSame(['.', '..'], scandir($this->root . '/sync-uploads'));
	}

	public function testCommitRequiresAllFiles(): void
	{
		$manifest = Manifest::fromJson(ManifestTest::json(['site.json' => 'new data']));

		try {
			$this->store->commit($manifest);
			self::fail('Expected a SyncException');
		} catch (SyncException $e) {
			self::assertSame(409, $e->status);
			self::assertSame([hash('sha256', 'new data')], $e->details);
		}

		self::assertFalse(is_link($this->data));
	}

	public function testCommitPublishesReleaseBehindSymlink(): void
	{
		$manifest = Manifest::fromJson(
			ManifestTest::json(['site.json' => 'new data', 'img/same.jpg' => 'same crop', 'md/pribeh.md' => 'story']),
		);
		$this->upload('new data');
		$this->upload('story');

		$release = $this->store->commit($manifest);

		self::assertTrue(is_link($this->data));
		self::assertSame('releases/' . $release, readlink($this->data));
		self::assertSame('new data', file_get_contents($this->data . '/site.json'));
		self::assertSame('same crop', file_get_contents($this->data . '/img/same.jpg'));
		self::assertSame('story', file_get_contents($this->data . '/md/pribeh.md'));
		self::assertSame($manifest->toJson(), file_get_contents($this->data . '/manifest.json'));
		self::assertTrue($this->store->isCurrent($manifest));
		self::assertFalse($this->store->isCurrent(Manifest::fromJson(ManifestTest::json(['site.json' => 'other']))));
		// The data that were there before are kept as the previous release; used uploads are removed.
		self::assertCount(2, (new Filesystem())->entries($this->root . '/releases'));
		self::assertSame([], (new Filesystem())->entries($this->root . '/sync-uploads'));
	}

	public function testOnlyTwoReleasesAreKept(): void
	{
		foreach (['one', 'two', 'three'] as $content) {
			$this->upload($content);
			$this->store->commit(Manifest::fromJson(ManifestTest::json(['site.json' => $content])));
		}

		self::assertCount(2, (new Filesystem())->entries($this->root . '/releases'));
		self::assertSame('three', file_get_contents($this->data . '/site.json'));
	}

	private function upload(string $content): void
	{
		$this->store->upload(hash('sha256', $content), (new StreamFactory())->createStream($content));
	}
}
