<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use Psr\Http\Message\StreamInterface;
use Rodokmen\Site\Config;
use RuntimeException;
use Throwable;
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
		return $this->missingIn($manifest, $this->sources($manifest));
	}

	/** Whether the website already shows exactly this bundle. */
	public function isCurrent(Manifest $manifest): bool
	{
		return $this->currentManifest()?->id() === $manifest->id();
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
			$sources = $this->sources($manifest);
			$missing = $this->missingIn($manifest, $sources);
			if ($missing !== []) {
				throw new SyncException('Some files have not been uploaded', 409, $missing);
			}

			// Sortable by time (microseconds, so that quick successive commits keep their order).
			$id = gmdate('Ymd-His') . '-' . substr(sprintf('%.6F', microtime(true)), -6) . '-' . substr($manifest->id(), 0, 8);
			$this->build($manifest, $sources, $this->releasesDir() . '/' . $id);
			$this->activate($id);
			$this->prune($manifest, $id);

			return $id;
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * Existing file with the expected content for every path of the manifest (null where there is none).
	 *
	 * @return array<string, string|null>
	 */
	private function sources(Manifest $manifest): array
	{
		// The current manifest describes the current data, so its hashes spare reading every file again –
		// except for files changed after it was written (an interrupted sync, a manual edit), which are hashed.
		$current = $this->currentManifest()?->files;
		$writtenAt = $current === null ? 0 : (int) filemtime($this->config->dataDir . '/' . Manifest::FileName);
		$sources = [];
		foreach ($manifest->files as $path => $file) {
			$sources[$path] = $this->source($path, $file, $current, $writtenAt);
		}

		return $sources;
	}

	/**
	 * Hashes of the manifest's files that have no source.
	 *
	 * @param array<string, string|null> $sources
	 * @return list<string>
	 */
	private function missingIn(Manifest $manifest, array $sources): array
	{
		$missing = [];
		foreach ($manifest->files as $path => $file) {
			if ($sources[$path] === null) {
				$missing[$file->sha256] = true;
			}
		}

		return array_keys($missing);
	}

	/**
	 * Existing file with the expected content: the same path in the current data, or an upload.
	 *
	 * @param array<string, ManifestFile>|null $current files of the current manifest, null when there is none
	 * @param int $writtenAt modification time of the current manifest
	 */
	private function source(string $path, ManifestFile $file, ?array $current, int $writtenAt): ?string
	{
		$existing = $this->config->dataDir . '/' . $path;
		if (is_file($existing) && filesize($existing) === $file->size) {
			$sha256 = $current === null || filemtime($existing) > $writtenAt
				? hash_file('sha256', $existing)
				: ($current[$path]->sha256 ?? null);
			if ($sha256 === $file->sha256) {
				return $existing;
			}
		}

		$uploaded = $this->uploadsDir() . '/' . $file->sha256;

		return is_file($uploaded) && filesize($uploaded) === $file->size ? $uploaded : null;
	}

	/** Manifest of the data the website shows now; null for data without one (e.g. copied by rsync). */
	private function currentManifest(): ?Manifest
	{
		$file = $this->config->dataDir . '/' . Manifest::FileName;
		$json = is_file($file) ? file_get_contents($file) : false;
		if ($json === false) {
			return null;
		}

		try {
			return Manifest::fromJson($json);
		} catch (InvalidManifestException) {
			return null;
		}
	}

	/** @param array<string, string|null> $sources */
	private function build(Manifest $manifest, array $sources, string $dir): void
	{
		foreach (array_keys($manifest->files) as $path) {
			$source = $sources[$path] ?? throw new SyncException('Some files have not been uploaded: ' . $path, 409);

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
