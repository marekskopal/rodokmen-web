<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_SH;
use const LOCK_UN;

/**
 * One locked file of LoginThrottle: the times of recent attempts as a JSON list. Open it, read and write it while
 * the lock is held, close it.
 */
final class ThrottleFile
{
	/** @param resource $handle */
	private function __construct(private $handle, private readonly int $window)
	{
	}

	/** Opens (creates) the file with an exclusive lock; null when it cannot be written. */
	public static function open(string $file, int $window): ?self
	{
		$dir = dirname($file);
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}

		// The cleanup may remove the file while this request waits for its lock: then the lock is on a removed file,
		// and what is written would be lost. Open the file again until the locked one is the one on disk.
		for ($try = 0; $try < 5; $try++) {
			$handle = @fopen($file, 'c+');
			if ($handle === false) {
				return null;
			}

			flock($handle, LOCK_EX);
			if (self::isOnDisk($handle, $file)) {
				return new self($handle, $window);
			}

			flock($handle, LOCK_UN);
			fclose($handle);
		}

		return null;
	}

	/**
	 * Removes the file when it holds no attempt within the window. A file that another request has locked is left
	 * alone (it is in use); the removal happens under the lock, see open(). A parallel cleanup may have removed the
	 * file meanwhile and a new one with fresh attempts may stand at the path: that one is not removed.
	 */
	public static function removeIfExpired(string $file, int $window): bool
	{
		$handle = @fopen($file, 'r+');
		if ($handle === false) {
			return false;
		}

		$removed = false;
		if (flock($handle, LOCK_EX | LOCK_NB)) {
			if (self::isOnDisk($handle, $file) && new self($handle, $window)->read() === []) {
				$removed = @unlink($file);
			}

			flock($handle, LOCK_UN);
		}

		fclose($handle);

		return $removed;
	}

	/**
	 * Attempts within the window, read under a shared lock (a file being rewritten is not read as empty).
	 *
	 * @return list<int>
	 */
	public static function peek(string $file, int $window): array
	{
		$handle = is_file($file) ? @fopen($file, 'r') : false;
		if ($handle === false) {
			return [];
		}

		flock($handle, LOCK_SH);
		$attempts = new self($handle, $window)->read();
		flock($handle, LOCK_UN);
		fclose($handle);

		return $attempts;
	}

	/**
	 * Whether the open handle is the file at the path (and not one removed meanwhile).
	 *
	 * @param resource $handle
	 */
	private static function isOnDisk($handle, string $file): bool
	{
		clearstatcache(true, $file);
		$onDisk = @stat($file);
		$open = fstat($handle);

		return $onDisk !== false && $open !== false && $onDisk['ino'] === $open['ino'] && $onDisk['dev'] === $open['dev'];
	}

	/** @return list<int> attempts within the window */
	public function read(): array
	{
		rewind($this->handle);
		$data = json_decode((string) stream_get_contents($this->handle), true);
		$limit = time() - $this->window;

		return array_values(array_filter(is_array($data) ? $data : [], static fn (mixed $t): bool => is_int($t) && $t > $limit));
	}

	/** @param list<int> $attempts */
	public function write(array $attempts): void
	{
		ftruncate($this->handle, 0);
		rewind($this->handle);
		fwrite($this->handle, (string) json_encode($attempts));
		fflush($this->handle);
	}

	public function close(): void
	{
		flock($this->handle, LOCK_UN);
		fclose($this->handle);
	}
}
