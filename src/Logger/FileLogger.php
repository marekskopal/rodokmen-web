<?php

declare(strict_types=1);

namespace Rodokmen\Site\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;
use function date;
use function fclose;
use function file_put_contents;
use function filesize;
use function fopen;
use function fwrite;
use function is_dir;
use function is_scalar;
use function is_string;
use function is_writable;
use function json_encode;
use function mkdir;
use function rename;
use function sprintf;
use function strtoupper;
use const DATE_ATOM;
use const FILE_APPEND;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;

/**
 * Writes one line per record to log/app.log (SITE_LOG_DIR), the folder Docker mounts as a volume.
 * Records below the minimum level (SITE_LOG_LEVEL, default notice) are dropped. When the folder is not
 * writable the records go to stderr, which Apache forwards to its error log (the container log in Docker).
 */
final class FileLogger extends AbstractLogger
{
	public const FileName = 'app.log';

	/** The log is rotated to app.log.1 when it grows over this size. */
	private const MaxSize = 10 * 1024 * 1024;

	public const Levels = [
		LogLevel::DEBUG => 0,
		LogLevel::INFO => 1,
		LogLevel::NOTICE => 2,
		LogLevel::WARNING => 3,
		LogLevel::ERROR => 4,
		LogLevel::CRITICAL => 5,
		LogLevel::ALERT => 6,
		LogLevel::EMERGENCY => 7,
	];

	private ?bool $writable = null;

	public function __construct(private readonly string $dir, private readonly string $minLevel = LogLevel::NOTICE)
	{
	}

	/** @param array<mixed> $context */
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$level = is_string($level) && isset(self::Levels[$level]) ? $level : LogLevel::ERROR;
		if (self::Levels[$level] < (self::Levels[$this->minLevel] ?? self::Levels[LogLevel::NOTICE])) {
			return;
		}

		$line = $this->format($level, (string) $message, $context);
		if ($this->isWritable()) {
			$file = $this->dir . '/' . self::FileName;
			if ((int) @filesize($file) > self::MaxSize) {
				@rename($file, $file . '.1');
			}
			if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false) {
				return;
			}
		}

		$stderr = fopen('php://stderr', 'w');
		if ($stderr === false) {
			return;
		}

		fwrite($stderr, $line);
		fclose($stderr);
	}

	/** @param array<mixed> $context */
	private function format(string $level, string $message, array $context): string
	{
		$exception = $context['exception'] ?? null;
		unset($context['exception']);

		$line = sprintf('[%s] %s: %s', date(DATE_ATOM), strtoupper($level), $message);
		$extra = [];
		foreach ($context as $key => $value) {
			$extra[$key] = is_scalar($value) || $value === null
				? $value
				: json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		if ($extra !== []) {
			$line .= ' ' . json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		if ($exception instanceof Throwable) {
			$line .= sprintf(
				"\n  %s: %s in %s:%d\n%s",
				$exception::class,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine(),
				$exception->getTraceAsString(),
			);
		}

		return $line . "\n";
	}

	private function isWritable(): bool
	{
		if ($this->writable === null) {
			$this->writable = (is_dir($this->dir) || @mkdir($this->dir, 0775, true)) && is_writable($this->dir);
		}

		return $this->writable;
	}
}
