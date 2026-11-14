<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use function array_filter;
use function array_values;
use function dirname;
use function fclose;
use function fflush;
use function flock;
use function fopen;
use function ftruncate;
use function fwrite;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function json_decode;
use function json_encode;
use function mkdir;
use function rewind;
use function stream_get_contents;
use function time;
use const LOCK_EX;
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

		$handle = @fopen($file, 'c+');
		if ($handle === false) {
			return null;
		}

		flock($handle, LOCK_EX);

		return new self($handle, $window);
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
