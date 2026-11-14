<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Sync;

use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Rodokmen\Site\Sync\DataStore;
use Rodokmen\Site\Sync\Filesystem;
use Rodokmen\Site\Sync\Manifest;
use Rodokmen\Site\Sync\Releases;
use Rodokmen\Site\Sync\SyncException;
use Rodokmen\Site\Tests\Support\Fixture;
use RuntimeException;

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
		$config = Fixture::config($this->data);
		$this->store = new DataStore($config, new Filesystem(), new Releases($config, new Filesystem()));
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

	public function testMissingTrustsTheCurrentManifestInsteadOfHashing(): void
	{
		// The manifest lists a hash the file does not have (same size): only a hash_file call would notice.
		file_put_contents(
			$this->data . '/' . Manifest::FileName,
			ManifestTest::json(['site.json' => 'old data', 'img/same.jpg' => 'SAME CROP']),
		);
		$manifest = Manifest::fromJson(ManifestTest::json(['site.json' => 'old data', 'img/same.jpg' => 'SAME CROP']));

		self::assertSame([], $this->store->missing($manifest));
	}

	public function testMissingHashesFilesChangedAfterTheCurrentManifest(): void
	{
		// The manifest lists a hash the file does not have, but the file is newer: it is hashed, not trusted.
		file_put_contents(
			$this->data . '/' . Manifest::FileName,
			ManifestTest::json(['site.json' => 'old data', 'img/same.jpg' => 'SAME CROP']),
		);
		touch($this->data . '/' . Manifest::FileName, time() - 10);
		$manifest = Manifest::fromJson(ManifestTest::json(['site.json' => 'old data', 'img/same.jpg' => 'SAME CROP']));

		self::assertSame([hash('sha256', 'SAME CROP')], $this->store->missing($manifest));
	}

	public function testMissingWithCurrentManifestNeedsUnlistedAndChangedFiles(): void
	{
		file_put_contents($this->data . '/' . Manifest::FileName, ManifestTest::json(['site.json' => 'old data']));
		$manifest = Manifest::fromJson(ManifestTest::json(['site.json' => 'new data', 'img/same.jpg' => 'same crop']));

		self::assertEqualsCanonicalizing(
			[hash('sha256', 'new data'), hash('sha256', 'same crop')],
			$this->store->missing($manifest),
		);
	}

	public function testCommitAfterCommitReusesFilesOfTheCurrentRelease(): void
	{
		$this->upload('new data');
		$this->store->commit(Manifest::fromJson(ManifestTest::json(['site.json' => 'new data', 'img/same.jpg' => 'same crop'])));

		$next = Manifest::fromJson(ManifestTest::json(['site.json' => 'new data', 'img/same.jpg' => 'same crop', 'md/a.md' => 'a']));
		self::assertSame([hash('sha256', 'a')], $this->store->missing($next));

		$this->upload('a');
		$this->store->commit($next);
		self::assertSame('same crop', file_get_contents($this->data . '/img/same.jpg'));
		self::assertSame('a', file_get_contents($this->data . '/md/a.md'));
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

	public function testFailedUploadLeavesNothingBehind(): void
	{
		$body = self::createStub(StreamInterface::class);
		$body->method('eof')->willReturn(false);
		$calls = 0;
		$body->method('read')->willReturnCallback(static function () use (&$calls): string {
			$calls++;
			if ($calls > 1) {
				throw new RuntimeException('Connection reset');
			}

			return 'partial content';
		});

		try {
			$this->store->upload(hash('sha256', 'whatever'), $body);
			self::fail('Expected a RuntimeException');
		} catch (RuntimeException $e) {
			self::assertSame('Connection reset', $e->getMessage());
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

	public function testRollbackSwitchesToThePreviousRelease(): void
	{
		$first = $this->publish('one');
		$second = $this->publish('two');

		self::assertSame($first, $this->store->rollback());
		self::assertSame('releases/' . $first, readlink($this->data));
		self::assertSame('one', file_get_contents($this->data . '/site.json'));
		// The release rolled back from is kept until the next commit, but there is nothing older to go back to.
		self::assertTrue(is_dir($this->root . '/releases/' . $second));
		$this->assertRollbackFails('There is no previous release to roll back to');
	}

	public function testCommitAfterRollbackKeepsTheRolledBackRelease(): void
	{
		$first = $this->publish('one');
		$second = $this->publish('two');
		$this->store->rollback();

		$third = $this->publish('three');

		self::assertEqualsCanonicalizing([$first, $third], (new Filesystem())->entries($this->root . '/releases'));
		self::assertFalse(is_dir($this->root . '/releases/' . $second));
		self::assertSame($first, $this->store->rollback());
		self::assertSame('one', file_get_contents($this->data . '/site.json'));
	}

	public function testRollbackReturnsToTheDataFromBeforeTheFirstUpload(): void
	{
		$this->publish('one');

		$release = $this->store->rollback();

		self::assertStringEndsWith('-initial', $release);
		self::assertSame('old data', file_get_contents($this->data . '/site.json'));
	}

	public function testInitialDataSortBeforeASlowlyBuiltFirstRelease(): void
	{
		// The release id is taken before the build; the data from before must still count as older.
		$id = '20200101-000000-000000-abcdef12';
		mkdir($this->root . '/releases/' . $id, 0777, true);
		$releases = new Releases(Fixture::config($this->data), new Filesystem());

		$initial = $releases->activate($id);

		self::assertSame('20191231-235959-000000-initial', $initial);
		self::assertSame($initial, $releases->previous());
	}

	public function testRollbackNeedsUploadedData(): void
	{
		$this->assertRollbackFails('There is no release to roll back from (the data were not uploaded by the sync API)');
		self::assertSame('old data', file_get_contents($this->data . '/site.json'));
	}

	private function assertRollbackFails(string $message): void
	{
		try {
			$this->store->rollback();
			self::fail('Expected a SyncException');
		} catch (SyncException $e) {
			self::assertSame(409, $e->status);
			self::assertSame($message, $e->getMessage());
		}
	}

	private function publish(string $content): string
	{
		$this->upload($content);

		return $this->store->commit(Manifest::fromJson(ManifestTest::json(['site.json' => $content])));
	}

	private function upload(string $content): void
	{
		$this->store->upload(hash('sha256', $content), (new StreamFactory())->createStream($content));
	}
}
