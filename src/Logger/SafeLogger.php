<?php

declare(strict_types=1);

namespace Rodokmen\Site\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;
use function date;
use function file_put_contents;
use function implode;
use function is_string;
use function strtoupper;
use const FILE_APPEND;
use const PHP_EOL;

/**
 * Wraps the application logger so that a logging fault can never break the request.
 *
 * Tracy throws when it cannot write its log file (missing or unwritable folder, full disk); the record then
 * goes to stderr (Apache's error log, the container log in Docker) instead of vanishing.
 */
final class SafeLogger extends AbstractLogger
{
	public function __construct(private readonly LoggerInterface $logger, private readonly string $fallbackStream = 'php://stderr')
	{
	}

	/** @param array<mixed> $context */
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		try {
			$this->logger->log($level, $message, $context);
		} catch (Throwable $e) {
			$line = implode(' ', [
				date('[Y-m-d H-i-s]'),
				'LOGGER FAILED: ' . $e->getMessage(),
				'| dropped ' . (is_string($level) ? strtoupper($level) : 'LOG') . ': ' . $message,
			]);

			try {
				@file_put_contents($this->fallbackStream, $line . PHP_EOL, FILE_APPEND);
			} catch (Throwable) {
				// Nothing left to write to: never let a logging fault propagate to the caller.
			}
		}
	}
}
