<?php

declare(strict_types=1);

namespace Rodokmen\Site\Logger;

use Throwable;
use Tracy\Logger;
use const FILE_APPEND;
use const PHP_EOL;

/**
 * Tracy's file logger (error.log, warning.log, info.log, exception-*.html in the log folder) that also writes
 * every record as one line to stderr, which Apache forwards to its error log – the container log in Docker.
 * Debug records are written only with SITE_LOG_LEVEL=debug.
 *
 * The files do not grow forever: a *.log file larger than MaxLogSize is renamed to *.log.1 (older ones to .2, .3,
 * the oldest is dropped), and exception reports older than ReportDays are removed when a new one is written.
 */
final class TracyLogger extends Logger
{
	private const MaxLogSize = 10 * 1024 * 1024;

	/** Rotated copies kept next to every log file (error.log.1 … error.log.3). */
	private const KeepRotated = 3;

	private const ReportDays = 30;

	public function __construct(?string $directory, private readonly bool $debug = false, private readonly ?string $stderr = 'php://stderr')
	{
		parent::__construct($directory);
	}

	public function log(mixed $message, string $level = self::INFO): ?string
	{
		if ($level === self::DEBUG && !$this->debug) {
			return null;
		}

		if ($this->directory !== null) {
			self::rotate($this->directory . '/' . strtolower($level !== '' ? $level : self::INFO) . '.log');
		}

		$exceptionFile = parent::log($message, $level);
		if ($exceptionFile !== null && $this->directory !== null) {
			self::removeOldReports($this->directory, $exceptionFile);
		}

		if ($this->stderr !== null) {
			$line = self::formatLogLine($message, $exceptionFile);
			if ($message instanceof Throwable) {
				$line .= PHP_EOL . strstr((string) $message, 'Stack trace:');
			}
			@file_put_contents($this->stderr, $line . PHP_EOL, FILE_APPEND);
		}

		return $exceptionFile;
	}

	private static function rotate(string $file): void
	{
		clearstatcache(true, $file);
		$size = @filesize($file);
		if ($size === false || $size < self::MaxLogSize) {
			return;
		}

		// Two requests may rotate at once: then one rotated copy is overwritten, no record of the new file is lost.
		@unlink($file . '.' . self::KeepRotated);
		for ($n = self::KeepRotated - 1; $n >= 1; $n--) {
			@rename($file . '.' . $n, $file . '.' . ($n + 1));
		}
		@rename($file, $file . '.1');
	}

	/** Exception reports (HTML, one per distinct exception) older than ReportDays, except the one just written. */
	private static function removeOldReports(string $directory, string $current): void
	{
		// scandir, not glob: the folder's path may contain glob metacharacters ([, *, ?).
		$names = @scandir($directory);
		$current = basename($current);
		$limit = time() - self::ReportDays * 86400;
		foreach ($names === false ? [] : $names as $name) {
			if ($name === $current || !str_contains($name, '--') || !str_ends_with($name, '.html')) {
				continue;
			}

			$report = $directory . '/' . $name;
			if ((int) @filemtime($report) < $limit) {
				@unlink($report);
			}
		}
	}
}
