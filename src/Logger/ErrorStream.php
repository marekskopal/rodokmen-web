<?php

declare(strict_types=1);

namespace Rodokmen\Site\Logger;

use const FILE_APPEND;
use const PHP_EOL;
use const PHP_SAPI;

/**
 * Writes a log line to stderr where the server's administrator looks for it.
 *
 * Under FastCGI (mod_fcgid, PHP-FPM) the process's own stderr goes to the main error log of Apache, or nowhere,
 * not to the site's log; PHP's SAPI logger (error_log() type 4) sends the line through the web server, which
 * writes it to the site's error log like PHP's own warnings. Elsewhere – mod_php, whose stderr is Apache's
 * error log (the container log in Docker), command-line runs and other streams (tests) – the line goes to the
 * stream itself, so that it keeps its own format and the whole stack trace.
 */
final readonly class ErrorStream
{
	public const Stderr = 'php://stderr';

	public static function write(string $stream, string $line): void
	{
		if (self::viaSapi($stream, PHP_SAPI)) {
			@error_log($line, 4);

			return;
		}

		@file_put_contents($stream, $line . PHP_EOL, FILE_APPEND);
	}

	/** Whether a line for the stream goes through the SAPI logger of the server API (PHP_SAPI) instead of the stream. */
	public static function viaSapi(string $stream, string $sapi): bool
	{
		return $stream === self::Stderr && in_array($sapi, ['cgi-fcgi', 'fpm-fcgi'], true);
	}
}
