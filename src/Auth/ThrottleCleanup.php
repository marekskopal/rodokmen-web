<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Rodokmen\Site\Config;
use function closedir;
use function ctype_digit;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function is_dir;
use function max;
use function microtime;
use function opendir;
use function readdir;
use function str_ends_with;
use function time;
use function touch;
use function trim;

/**
 * Removes throttle files without a recent attempt: every client address (and every known browser) gets a file of its
 * own, so without a cleanup an attacker changing addresses could fill the disk. Runs now and then from the throttle
 * itself, at most once per Interval and within a time budget, so that no request waits long.
 */
final readonly class ThrottleCleanup
{
	private const Interval = 60;

	/** Seconds one cleanup may take; what is left is removed by the next one. */
	private const Budget = 0.2;

	private const Marker = '.cleanup';

	public function __construct(private Config $config)
	{
	}

	/** Cleans up when the last cleanup is older than Interval. */
	public function maybeRun(int $window): void
	{
		$dir = $this->dir();
		if (!is_dir($dir)) {
			return;
		}

		$last = @filemtime($dir . '/' . self::Marker);
		if ($last !== false && $last > time() - self::Interval) {
			return;
		}

		// Touched first, so that parallel requests do not all start a cleanup. A directory that cannot be written holds
		// no new throttle files either; without the marker every request would run a cleanup.
		if (!@touch($dir . '/' . self::Marker)) {
			return;
		}

		$this->run($window, self::Budget);
	}

	/**
	 * Removes the files without an attempt within the window; returns how many. When the budget runs out, the
	 * position is kept in the marker file and the next run goes on from there, so that files further on are reached
	 * even when there are more than one run can check.
	 */
	public function run(int $window, float $budget): int
	{
		$dir = $this->dir();
		$handle = is_dir($dir) ? @opendir($dir) : false;
		if ($handle === false) {
			return 0;
		}

		$deadline = microtime(true) + $budget;
		$skip = $this->position();
		$position = 0;
		$removed = 0;
		$finished = true;
		while (($name = readdir($handle)) !== false) {
			$position++;
			if ($position <= $skip) {
				continue;
			}

			if ($this->removeIfExpired($dir . '/' . $name, $name, $window)) {
				$removed++;
			}

			if (microtime(true) > $deadline) {
				$finished = false;

				break;
			}
		}

		closedir($handle);
		// Removed files shift the listing a little: some files are checked twice or a round later, none is lost.
		$this->savePosition($finished ? 0 : $position - $removed);

		return $removed;
	}

	private function removeIfExpired(string $file, string $name, int $window): bool
	{
		// All the throttle's files are *.json, also those of older versions (<hash>.json). A file written within the
		// window may hold a recent attempt: skipped by its time alone, without opening it.
		if (!str_ends_with($name, '.json')) {
			return false;
		}

		$modified = @filemtime($file);

		return $modified !== false && $modified <= time() - $window && ThrottleFile::removeIfExpired($file, $window);
	}

	private function position(): int
	{
		$position = @file_get_contents($this->dir() . '/' . self::Marker);

		return $position !== false && ctype_digit(trim($position)) ? (int) trim($position) : 0;
	}

	private function savePosition(int $position): void
	{
		// Writing it also renews its time: the next cleanup is due an Interval after this one ended.
		@file_put_contents($this->dir() . '/' . self::Marker, (string) max(0, $position));
	}

	private function dir(): string
	{
		return $this->config->cacheDir . '/throttle';
	}
}
