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
 */
final class TracyLogger extends Logger
{
	public function __construct(?string $directory, private readonly bool $debug = false, private readonly ?string $stderr = 'php://stderr')
	{
		parent::__construct($directory);
	}

	public function log(mixed $message, string $level = self::INFO): ?string
	{
		if ($level === self::DEBUG && !$this->debug) {
			return null;
		}

		$exceptionFile = parent::log($message, $level);

		if ($this->stderr !== null) {
			$line = self::formatLogLine($message, $exceptionFile);
			if ($message instanceof Throwable) {
				$line .= PHP_EOL . strstr((string) $message, 'Stack trace:');
			}
			@file_put_contents($this->stderr, $line . PHP_EOL, FILE_APPEND);
		}

		return $exceptionFile;
	}
}
