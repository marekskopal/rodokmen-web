<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use RuntimeException;
use function is_dir;
use function is_link;
use function mkdir;
use function rmdir;
use function scandir;
use function unlink;

/** Small directory helpers of the data store. */
final readonly class Filesystem
{
	/** @return list<string> names in the directory, without . and .. */
	public function entries(string $dir): array
	{
		$names = is_dir($dir) ? scandir($dir) : false;
		$entries = [];
		foreach ($names === false ? [] : $names as $name) {
			if ($name !== '.' && $name !== '..') {
				$entries[] = $name;
			}
		}

		return $entries;
	}

	/** Removes a directory tree; symlinks are removed, never followed. */
	public function removeDir(string $dir): void
	{
		foreach ($this->entries($dir) as $name) {
			$path = $dir . '/' . $name;
			if (is_dir($path) && !is_link($path)) {
				$this->removeDir($path);
			} else {
				@unlink($path);
			}
		}
		@rmdir($dir);
	}

	public function ensureDir(string $dir): void
	{
		if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new RuntimeException('Cannot create ' . $dir);
		}
	}
}
