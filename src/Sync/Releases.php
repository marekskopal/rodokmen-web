<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use DateTimeImmutable;
use DateTimeZone;
use Rodokmen\Site\Config;
use RuntimeException;

/**
 * Data releases next to the data directory: releases/<id>/ holds complete bundles and the data directory is
 * a symlink to the one the website shows. Ids sort by the time of the commit.
 */
final readonly class Releases
{
	public function __construct(private Config $config, private Filesystem $fs)
	{
	}

	public function dir(): string
	{
		return dirname($this->config->dataDir) . '/releases';
	}

	/** Release the data directory points at; null when it is not a release (no upload over the API yet). */
	public function current(): ?string
	{
		$target = is_link($this->config->dataDir) ? readlink($this->config->dataDir) : false;

		return $target === false ? null : basename($target);
	}

	/** Newest release older than the current one – the one a rollback returns to. */
	public function previous(): string
	{
		$current = $this->current();
		if ($current === null) {
			throw new SyncException('There is no release to roll back from (the data were not uploaded by the sync API)', 409);
		}

		$older = array_filter(
			$this->fs->entries($this->dir()),
			fn (string $release): bool => $release < $current && is_dir($this->dir() . '/' . $release),
		);
		if ($older === []) {
			throw new SyncException('There is no previous release to roll back to', 409);
		}

		return max($older);
	}

	/**
	 * Points the data directory at the release; the symlink is replaced atomically.
	 * Returns the release shown before (null when there was none).
	 */
	public function activate(string $id): ?string
	{
		$data = $this->config->dataDir;
		$previous = $this->current();
		if (is_dir($data) && !is_link($data)) {
			// First upload: keep the data that was there (e.g. copied by rsync) as an older release. Its id must sort
			// before the new one (rollback goes to older ids), even when building the release took a while.
			$previous = $this->idBefore($id) . '-000000-initial';
			$this->fs->ensureDir($this->dir());
			if (!rename($data, $this->dir() . '/' . $previous)) {
				throw new RuntimeException('Cannot keep ' . $data . ' as release ' . $previous);
			}
		}

		$tmp = sprintf('%s/.%s.%s.link', dirname($data), basename($data), bin2hex(random_bytes(4)));
		@unlink($tmp);
		if (!symlink('releases/' . $id, $tmp) || !rename($tmp, $data)) {
			throw new RuntimeException('Cannot switch ' . $data . ' to release ' . $id);
		}

		return $previous;
	}

	/** Removes all releases except the current one and the one shown before it (to roll back to). */
	public function keepOnly(string $current, ?string $previous): void
	{
		foreach ($this->fs->entries($this->dir()) as $release) {
			if ($release !== $current && $release !== $previous) {
				$this->fs->removeDir($this->dir() . '/' . $release);
			}
		}
	}

	/** Time prefix (Ymd-His) one second before the release id's own. */
	private function idBefore(string $id): string
	{
		$time = DateTimeImmutable::createFromFormat('!Ymd-His', substr($id, 0, 15), new DateTimeZone('UTC'));
		if ($time === false) {
			throw new RuntimeException('Invalid release id ' . $id);
		}

		return $time->modify('-1 second')->format('Ymd-His');
	}
}
