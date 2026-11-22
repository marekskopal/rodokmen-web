<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

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

	/**
	 * Copies $source to $target with the source's modification time, as a hard link would keep it (used when the
	 * uploads are on another filesystem): the WebP thumbnails are named after it, so an unchanged image keeps them.
	 */
	public function copyKeepingTime(string $source, string $target): void
	{
		$modified = @filemtime($source);
		if ($modified === false || !@copy($source, $target) || !@touch($target, $modified)) {
			throw self::failure('Cannot copy ' . $source . ' to ' . $target);
		}
	}

	public function ensureDir(string $dir): void
	{
		if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw self::failure('Cannot create ' . $dir);
		}
	}

	/**
	 * Writes the stream into a new file and returns its SHA-256. Stops with 413 when the stream is longer than
	 * $maxSize bytes; a failed write throws. The caller removes the file when anything fails.
	 */
	public function writeStream(StreamInterface $body, string $file, int $maxSize, int $chunkSize): string
	{
		// A failed write may leave no warning (a short fwrite()): an older one must not be given as its reason.
		error_clear_last();
		$out = @fopen($file, 'wb');
		if ($out === false) {
			throw self::failure('Cannot write ' . $file);
		}

		$hash = hash_init('sha256');
		$size = 0;

		try {
			while (!$body->eof()) {
				$chunk = $body->read($chunkSize);
				if ($chunk === '') {
					// A stream that is not at its end but returns nothing would loop forever; the hash check catches a cut.
					break;
				}
				$size += strlen($chunk);
				if ($size > $maxSize) {
					throw new SyncException('File is larger than ' . $maxSize . ' bytes', 413);
				}
				hash_update($hash, $chunk);
				if (@fwrite($out, $chunk) !== strlen($chunk)) {
					throw self::failure('Cannot write ' . $file, 'disk full?');
				}
			}
		} finally {
			$closed = @fclose($out);
		}

		if (!$closed) {
			throw self::failure('Cannot write ' . $file, 'disk full?');
		}

		return hash_final($hash);
	}

	/**
	 * An exception for a failed file operation with the reason PHP gave for it (e.g. "Read-only file system",
	 * "Disk quota exceeded", "Permission denied"), so that the log and the sync client say what is wrong.
	 * $guess is added in brackets when PHP gave no reason.
	 */
	public static function failure(string $message, string $guess = ''): RuntimeException
	{
		$reason = error_get_last()['message'] ?? '';
		error_clear_last();

		if ($reason !== '') {
			return new RuntimeException($message . ': ' . $reason);
		}

		return new RuntimeException($guess === '' ? $message : $message . ' (' . $guess . ')');
	}
}
