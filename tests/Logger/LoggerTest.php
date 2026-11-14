<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Rodokmen\Site\Logger\Logger;
use Rodokmen\Site\Logger\SafeLogger;
use Rodokmen\Site\Logger\TracyLogger;
use Rodokmen\Site\Tests\Support\Fixture;
use RuntimeException;
use Stringable;

final class LoggerTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/rodokmen-log-' . bin2hex(random_bytes(6));
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testWritesTracyLogFilesByLevelAndCreatesTheFolder(): void
	{
		$logger = Logger::initLogger($this->dir, stderr: false);
		$logger->warning('Wrong password', ['ip' => '10.0.0.1']);
		$logger->info('Data published');
		$logger->debug('Details');

		self::assertStringContainsString('Wrong password', $this->read('warning.log'));
		self::assertStringContainsString('10.0.0.1', $this->read('warning.log'));
		self::assertStringContainsString('Data published', $this->read('info.log'));
		self::assertFalse(is_file($this->dir . '/debug.log'));

		Logger::initLogger($this->dir, debug: true, stderr: false)->debug('Details');
		self::assertStringContainsString('Details', $this->read('debug.log'));
	}

	public function testLogsExceptionWithBlueScreen(): void
	{
		Logger::initLogger($this->dir, stderr: false)->error('Failed', ['exception' => new RuntimeException('Boom')]);

		self::assertStringContainsString('RuntimeException: Boom', $this->read('error.log'));
		self::assertNotSame([], glob($this->dir . '/error--*.html'));
	}

	public function testCopiesRecordsToStderrStream(): void
	{
		$stream = (string) tempnam(sys_get_temp_dir(), 'rodokmen-stderr-');
		(new TracyLogger($this->dirCreated(), false, $stream))->log(new RuntimeException('Boom'), TracyLogger::ERROR);
		$written = (string) file_get_contents($stream);
		unlink($stream);

		self::assertStringContainsString('RuntimeException: Boom', $written);
		self::assertStringContainsString('Stack trace:', $written);
	}

	public function testUnwritableFolderFallsBackWithoutFailing(): void
	{
		file_put_contents($this->dir, 'a file, not a folder');
		$this->expectNotToPerformAssertions();

		try {
			// The record goes to stderr ("LOGGER FAILED …") instead of breaking the request.
			Logger::initLogger($this->dir . '/sub', stderr: false)->error('Still fine');
		} finally {
			unlink($this->dir);
		}
	}

	public function testSafeLoggerReportsAFaultOnTheFallbackStream(): void
	{
		$inner = new class extends AbstractLogger {
			/** @param array<mixed> $context */
			public function log(mixed $level, string|Stringable $message, array $context = []): void
			{
				throw new RuntimeException('Unable to write to log file');
			}
		};
		$fallback = (string) tempnam(sys_get_temp_dir(), 'rodokmen-safe-');

		(new SafeLogger($inner, $fallback))->error('Lost record');
		$written = (string) file_get_contents($fallback);
		unlink($fallback);

		self::assertStringContainsString('LOGGER FAILED: Unable to write to log file | dropped ERROR: Lost record', $written);
	}

	private function dirCreated(): string
	{
		Logger::initLogger($this->dir, stderr: false);
		touch($this->dir . '/.keep');

		return $this->dir;
	}

	private function read(string $file): string
	{
		return (string) file_get_contents($this->dir . '/' . $file);
	}
}
