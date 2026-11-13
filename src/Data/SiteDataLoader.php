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
		$key = md5((string) realpath($json) . '|' . filemtime($json) . '|' . filesize($json));
		$cache = $this->config->cacheDir . '/site-' . $key . '.php';
		if (is_file($cache)) {
			return require $cache;
		}

		$contents = file_get_contents($json);
		if ($contents === false) {
			throw new RuntimeException('Cannot read ' . $json);
		}

		$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		$this->writeCache($cache, $data);

		return $data;
	}

	private function writeCache(string $cache, mixed $data): void
	{
		if (!is_dir($this->config->cacheDir)) {
			@mkdir($this->config->cacheDir, 0775, true);
		}

		$old = glob($this->config->cacheDir . '/site-*.php');
		foreach ($old === false ? [] : $old as $file) {
			@unlink($file);
		}

		$tmp = $cache . '.' . getmypid() . '.tmp';
		if (@file_put_contents($tmp, '<?php return ' . var_export($data, true) . ';') !== false) {
			@rename($tmp, $cache);
		}
	}
}
