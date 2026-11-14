<?php

declare(strict_types=1);

namespace Rodokmen\Site\Logger;

use Psr\Log\LoggerInterface;
use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;
use Tracy\Debugger;
use const E_ALL;

/**
 * Sets up logging with Tracy, as in the author's other projects: Tracy writes the log files, the application
 * logs through PSR-3 (LoggerInterface).
 */
final readonly class Logger
{
	/**
	 * @param bool $handleErrors register Tracy's error and exception handlers (production requests);
	 *                           off in tests, which must not change the global error handling
	 */
	public static function initLogger(
		string $directory,
		bool $debug = false,
		bool $stderr = true,
		bool $handleErrors = false,
	): LoggerInterface
	{
		if (!is_dir($directory)) {
			@mkdir($directory, 0775, true);
		}
		// Tracy stops the process when its log folder is missing; without one the records go to stderr (SafeLogger).
		$directory = is_dir($directory) && is_writable($directory) ? $directory : null;

		$tracyLogger = new TracyLogger($directory, $debug, $stderr ? 'php://stderr' : null);

		if ($handleErrors) {
			Debugger::setLogger($tracyLogger);
			mb_internal_encoding('UTF-8');
			error_reporting(E_ALL);
			ini_set('display_errors', 'stderr');
			// Turn warnings and notices into exceptions (the @ operator still silences them).
			Debugger::$strictMode = true;
			Debugger::enable(Debugger::Production, $directory);
		}

		return new SafeLogger(new TracyToPsrLoggerAdapter($tracyLogger));
	}
}
