<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use Psr\Http\Message\StreamInterface;
use Rodokmen\Site\Config;
use RuntimeException;
use Throwable;
use function array_keys;
use function array_slice;
use function basename;
use function bin2hex;
use function copy;
use function dirname;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function filesize;
use function flock;
use function fopen;
use function gmdate;
use function hash_file;
use function is_dir;
use function is_file;
use function is_link;
use function link;
use function preg_match;
use function random_bytes;
use function rename;
use function rsort;
use function sprintf;
use function substr;
use function symlink;
use function time;
use function unlink;
use const LOCK_EX;
use const LOCK_UN;

/**
 * Receives data bundles over the sync API and publishes them atomically.
 *
 * Next to the data directory (SITE_DATA_DIR, by default var/data) it keeps:
 *  - sync-uploads/<sha256> – uploaded files, addressed by their content,
 *  - releases/<id>/ – complete bundles; the data directory becomes a symlink to the current one.
 * Files that did not change are hard-linked from the current data, so a release costs only the new files.
 */
final readonly class DataStore
{
	public const MaxFileSize = 64 * 1024 * 1024;

	/** Releases kept on disk (the current one and the previous one, for a manual rollback). */
	private const KeepReleases = 2;

	/** Uploads not used by any commit are removed after this many seconds. */
	private const UploadTtl = 86400;

	private const ChunkSize = 1048576;

	public function __construct(private Config $config, private Filesystem $fs)
	{
	}

	/**
	 * Hashes of the manifest's files that the server does not have yet (neither in the current data nor uploaded).
	 *
	 * @return list<string>
	 */
	public function missing(Manifest $manifest): array
	{
		$missing = [];
		foreach ($manifest->files as $path => $file) {
			if ($this->source($path, $file) === null) {
				$missing[$file->sha256] = true;
			}
		}

		return array_keys($missing);
	}

	/** Whether the website already shows exactly this bundle. */
	public function isCurrent(Manifest $manifest): bool
	{
		$file = $this->config->dataDir . '/' . Manifest::FileName;
		$json = is_file($file) ? file_get_contents($file) : false;
		if ($json === false) {
			return false;
		}

		try {
			return Manifest::fromJson($json)->id() === $manifest->id();
		} catch (InvalidManifestException) {
			return false;
		}
	}

	/** Stores an uploaded file under its hash after checking that the content matches it. */
	public function upload(string $sha256, StreamInterface $body): void
	{
		if (preg_match('/^[0-9a-f]{64}$/', $sha256) !== 1) {
			throw new SyncException('Invalid hash', 400);
		}

		$dir = $this->uploadsDir();
		$this->fs->ensureDir($dir);
		$tmp = sprintf('%s/.%s.%s.tmp', $dir, $sha256, bin2hex(random_bytes(4)));

		try {
			$hash = $this->fs->writeStream($body, $tmp, self::MaxFileSize, self::ChunkSize);

			if ($hash !== $sha256) {
				throw new SyncException('Uploaded content does not match its hash', 422);
			}

			if (!rename($tmp, $dir . '/' . $sha256)) {
				throw new RuntimeException('Cannot store the upload ' . $sha256);
			}
		} catch (Throwable $e) {
			// A failed upload must not leave a partial file behind (it is not used, only removed by a later commit).
			@unlink($tmp);

			throw $e;
		}
	}

	/**
	 * Builds a release from the manifest and switches the website to it. Returns the release id.
	 */
	public function commit(Manifest $manifest): string
	{
		$this->fs->ensureDir($this->root());
		$lock = fopen($this->root() . '/sync.lock', 'c');
		if ($lock === false || !flock($lock, LOCK_EX)) {
			throw new RuntimeException('Cannot lock ' . $this->root() . '/sync.lock');
		}

		try {
			$missing = $this->missing($manifest);
			if ($missing !== []) {
				throw new SyncException('Some files have not been uploaded', 409, $missing);
			}

			// Sortable by time (microseconds, so that quick successive commits keep their order).
			$id = gmdate('Ymd-His') . '-' . substr(sprintf('%.6F', microtime(true)), -6) . '-' . substr($manifest->id(), 0, 8);
			$this->build($manifest, $this->releasesDir() . '/' . $id);
			$this->activate($id);
			$this->prune($manifest, $id);

			return $id;
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/** Existing file with the expected content: the same path in the current data, or an upload. */
	private function source(string $path, ManifestFile $file): ?string
	{
		$current = $this->config->dataDir . '/' . $path;
		if (is_file($current) && filesize($current) === $file->size && hash_file('sha256', $current) === $file->sha256) {
			return $current;
		}

		$uploaded = $this->uploadsDir() . '/' . $file->sha256;

		return is_file($uploaded) && filesize($uploaded) === $file->size ? $uploaded : null;
	}

	private function build(Manifest $manifest, string $dir): void
	{
		foreach ($manifest->files as $path => $file) {
			$source = $this->source($path, $file);
			if ($source === null) {
				throw new SyncException('File disappeared during the commit: ' . $path, 409);
			}

			$target = $dir . '/' . $path;
			$this->fs->ensureDir(dirname($target));
			if (!@link($source, $target) && !copy($source, $target)) {
				throw new RuntimeException('Cannot copy ' . $source . ' to ' . $target);
			}
		}

		file_put_contents($dir . '/' . Manifest::FileName, $manifest->toJson());
	}

	/** Points the data directory at the release; the symlink is replaced atomically. */
	private function activate(string $id): void
	{
		$data = $this->config->dataDir;
		if (is_dir($data) && !is_link($data)) {
			// First upload: keep the data that was there (e.g. copied by rsync) as an older release.
			rename($data, $this->releasesDir() . '/' . gmdate('Ymd-His', time() - 1) . '-000000-initial');
		}

		$tmp = sprintf('%s/.%s.%s.link', dirname($data), basename($data), bin2hex(random_bytes(4)));
		@unlink($tmp);
		if (!symlink('releases/' . $id, $tmp) || !rename($tmp, $data)) {
			throw new RuntimeException('Cannot switch ' . $data . ' to release ' . $id);
		}
	}

	private function prune(Manifest $manifest, string $current): void
	{
		$releases = $this->fs->entries($this->releasesDir());
		rsort($releases);
		foreach (array_slice($releases, self::KeepReleases) as $old) {
			if ($old === $current) {
				continue;
			}

			$this->fs->removeDir($this->releasesDir() . '/' . $old);
		}

		$used = [];
		foreach ($manifest->files as $file) {
			$used[$file->sha256] = true;
		}
		foreach ($this->fs->entries($this->uploadsDir()) as $name) {
			$path = $this->uploadsDir() . '/' . $name;
			if (isset($used[$name]) || (int) filemtime($path) < time() - self::UploadTtl) {
				@unlink($path);
			}
		}
	}

	private function root(): string
	{
		return dirname($this->config->dataDir);
	}

	private function uploadsDir(): string
	{
		return $this->root() . '/sync-uploads';
	}

	private function releasesDir(): string
	{
		return $this->root() . '/releases';
	}
}
