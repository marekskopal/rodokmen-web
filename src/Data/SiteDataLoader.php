<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use Rodokmen\Site\Config;
use Rodokmen\Site\Data\Model\SiteData;
use RuntimeException;
use const JSON_THROW_ON_ERROR;

/**
 * Loads site.json from the data directory into the readonly models. The decoded JSON is cached as a PHP file,
 * so OPcache keeps it in memory and only the (cheap, validating) hydration runs per request.
 */
final readonly class SiteDataLoader
{
	public function __construct(private Config $config)
	{
	}

	public function load(): SiteData
	{
		return SiteData::fromReader(ArrayReader::of($this->decoded()));
	}

	private function decoded(): mixed
	{
		$json = $this->config->dataDir . '/site.json';
		if (!is_file($json)) {
			throw new RuntimeException('Data not found: upload them with bin/rodokmen-sync first (' . $json . ')');
		}

		// The real path is part of the key: a sync release replaces the data directory behind a symlink.
		$realPath = (string) realpath($json);
		$key = md5($realPath . '|' . filemtime($json) . '|' . filesize($json));
		$cache = $this->config->cacheDir . '/site-' . $key . '.php';
		// Another request may remove the file between the check and the include (a new release replaced it):
		// include, unlike require, then only fails, and the JSON is decoded instead.
		$cached = is_file($cache) ? @include $cache : false;
		if ($cached !== false) {
			return $cached;
		}

		$contents = file_get_contents($json);
		if ($contents === false) {
			throw new RuntimeException('Cannot read ' . $json);
		}

		$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		$this->writeCache($cache, $data, $json, $realPath);

		return $data;
	}

	private function writeCache(string $cache, mixed $data, string $json, string $realPath): void
	{
		if (!is_dir($this->config->cacheDir)) {
			@mkdir($this->config->cacheDir, 0775, true);
		}

		$tmp = $cache . '.' . getmypid() . '.tmp';
		$code = '<?php return ' . var_export($data, true) . ';';
		// A short write (full disk) must not become the cache: a truncated file would fail every request.
		if (@file_put_contents($tmp, $code) !== strlen($code) || !@rename($tmp, $cache)) {
			@unlink($tmp);

			return;
		}

		// A request that started before a new release went live must not remove the new release's cache.
		if (realpath($json) !== $realPath) {
			return;
		}

		// Old caches are removed only after the new one is in place, so a request still working with the previous
		// release finds its file or falls back to the JSON.
		$old = glob($this->config->cacheDir . '/site-*.php');
		foreach ($old === false ? [] : $old as $file) {
			if ($file !== $cache) {
				@unlink($file);
			}
		}
	}
}
