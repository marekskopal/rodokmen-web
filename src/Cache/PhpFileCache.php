<?php

declare(strict_types=1);

namespace Rodokmen\Site\Cache;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 cache in PHP files (one per key): OPcache keeps them in memory, so a hit costs only unserialize().
 *
 * Every entry is stored with the version given to the constructor (e.g. a fingerprint of the code it was computed
 * from); an entry of another version counts as missing and is overwritten by the next set().
 */
final readonly class PhpFileCache implements CacheInterface
{
	public function __construct(private string $directory, private string $version = '')
	{
	}

	public function get(string $key, mixed $default = null): mixed
	{
		$serialized = $this->serialized($key);
		if ($serialized === null) {
			return $default;
		}

		// A value that cannot be unserialized (a corrupted entry) is a miss, not false handed to the caller.
		$value = @unserialize($serialized);

		return $value === false && $serialized !== serialize(false) ? $default : $value;
	}

	public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
	{
		$expires = $this->expires($ttl);
		if ($expires !== null && $expires <= time()) {
			return $this->delete($key);
		}

		if (!is_dir($this->directory)) {
			@mkdir($this->directory, 0775, true);
		}

		$file = $this->file($key);
		$tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
		$entry = ['version' => $this->version, 'expires' => $expires, 'value' => serialize($value)];
		$code = '<?php return ' . var_export($entry, true) . ';' . "\n";
		// A short write (full disk) must not become the entry: a truncated file would break every request.
		if (@file_put_contents($tmp, $code) !== strlen($code) || !@rename($tmp, $file)) {
			@unlink($tmp);

			return false;
		}

		$this->invalidate($file);

		return true;
	}

	public function delete(string $key): bool
	{
		$file = $this->file($key);
		$this->invalidate($file);

		return !is_file($file) || @unlink($file);
	}

	public function clear(): bool
	{
		$files = glob($this->directory . '/*.php');
		$cleared = true;
		foreach ($files === false ? [] : $files as $file) {
			$this->invalidate($file);
			$cleared = @unlink($file) && $cleared;
		}

		return $cleared;
	}

	/** @return iterable<string, mixed> */
	public function getMultiple(iterable $keys, mixed $default = null): iterable
	{
		$values = [];
		foreach ($keys as $key) {
			$values[$key] = $this->get($key, $default);
		}

		return $values;
	}

	/** @param iterable<mixed, mixed> $values */
	public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
	{
		$stored = true;
		foreach ($values as $key => $value) {
			// Array keys like "1" become integers in PHP.
			if (!is_string($key) && !is_int($key)) {
				throw new InvalidCacheKeyException('Cache keys must be strings');
			}

			$stored = $this->set((string) $key, $value, $ttl) && $stored;
		}

		return $stored;
	}

	/** @param iterable<string> $keys */
	public function deleteMultiple(iterable $keys): bool
	{
		$deleted = true;
		foreach ($keys as $key) {
			$deleted = $this->delete($key) && $deleted;
		}

		return $deleted;
	}

	public function has(string $key): bool
	{
		return $this->serialized($key) !== null;
	}

	/** Serialized value of the entry; null when there is none, it is of another version or it has expired. */
	private function serialized(string $key): ?string
	{
		$file = $this->file($key);
		// Another request may replace the file between the check and the include: include then only fails.
		$entry = is_file($file) ? @include $file : false;
		if (!is_array($entry) || ($entry['version'] ?? null) !== $this->version || !is_string($entry['value'] ?? null)) {
			return null;
		}

		$expires = $entry['expires'] ?? null;

		return $expires === null || (is_int($expires) && $expires > time()) ? $entry['value'] : null;
	}

	private function file(string $key): string
	{
		if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key) !== 1) {
			throw new InvalidCacheKeyException('Invalid cache key: ' . $key);
		}

		return $this->directory . '/' . $key . '.php';
	}

	private function expires(null|int|DateInterval $ttl): ?int
	{
		return match (true) {
			$ttl === null => null,
			$ttl instanceof DateInterval => (new DateTimeImmutable())->add($ttl)->getTimestamp(),
			default => time() + $ttl,
		};
	}

	/** With opcache.validate_timestamps=0 (the Docker image) OPcache would keep serving the old file. */
	private function invalidate(string $file): void
	{
		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($file, true);
		}
	}
}
