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

	public function testRotatesLargeLogFiles(): void
	{
		$dir = $this->dirCreated();
		$logger = new TracyLogger($dir, false, null);
		$this->grow($dir . '/warning.log', 'oldest');
		file_put_contents($dir . '/warning.log.3', 'dropped');
		file_put_contents($dir . '/warning.log.2', 'third');
		file_put_contents($dir . '/warning.log.1', 'second');

		$logger->log('Fresh record', TracyLogger::WARNING);

		self::assertStringContainsString('Fresh record', $this->read('warning.log'));
		self::assertLessThan(1000, (int) filesize($dir . '/warning.log'));
		self::assertStringStartsWith('oldest', $this->read('warning.log.1'));
		self::assertSame('second', $this->read('warning.log.2'));
		self::assertSame('third', $this->read('warning.log.3'));
		self::assertFileDoesNotExist($dir . '/warning.log.4');

		// A small file is left alone.
		$logger->log('Another', TracyLogger::WARNING);
		self::assertStringContainsString('Fresh record', $this->read('warning.log'));
	}

	public function testRemovesOldExceptionReports(): void
	{
		$dir = $this->dirCreated();
		file_put_contents($dir . '/error--2026-01-01--10-00--0123456789.html', 'old');
		touch($dir . '/error--2026-01-01--10-00--0123456789.html', time() - 31 * 86400);
		file_put_contents($dir . '/error--2026-11-01--10-00--abcdefabcd.html', 'recent');

		new TracyLogger($dir, false, null)->log(new RuntimeException('Boom'), TracyLogger::ERROR);

		$reports = glob($dir . '/*.html');
		self::assertIsArray($reports);
		self::assertCount(2, $reports, 'the recent one and the new one');
		self::assertFileDoesNotExist($dir . '/error--2026-01-01--10-00--0123456789.html');
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

	/** Makes the file just larger than the rotation limit (sparse, so the test stays fast). */
	private function grow(string $file, string $start): void
	{
		file_put_contents($file, $start);
		$handle = fopen($file, 'r+');
		self::assertNotFalse($handle);
		ftruncate($handle, 10 * 1024 * 1024 + 1);
		fclose($handle);
	}
}
