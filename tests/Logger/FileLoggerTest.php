<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Rodokmen\Site\Logger\FileLogger;
use Rodokmen\Site\Tests\Support\Fixture;
use RuntimeException;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function random_bytes;
use function str_repeat;
use function sys_get_temp_dir;

final class FileLoggerTest extends TestCase
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

	public function testWritesRecordsWithContextAndCreatesTheFolder(): void
	{
		(new FileLogger($this->dir))->warning('Wrong password', ['ip' => '10.0.0.1', 'tries' => [1, 2]]);

		$log = $this->log();
		self::assertMatchesRegularExpression(
			'/^\[\d{4}-\d\d-\d\dT[^\]]+\] WARNING: Wrong password \{"ip":"10\.0\.0\.1","tries":"\[1,2\]"\}\n$/',
			$log,
		);
	}

	public function testDropsRecordsBelowTheMinimumLevel(): void
	{
		$logger = new FileLogger($this->dir, LogLevel::WARNING);
		$logger->notice('Data published');
		$logger->info('Detail');
		$logger->error('Broken');

		self::assertStringNotContainsString('Data published', $this->log());
		self::assertStringContainsString('ERROR: Broken', $this->log());

		$debug = new FileLogger($this->dir, LogLevel::DEBUG);
		$debug->debug('Everything');
		self::assertStringContainsString('DEBUG: Everything', $this->log());
	}

	public function testUnknownLevelsAreErrorsAndUnknownMinimumIsNotice(): void
	{
		$logger = new FileLogger($this->dir, 'loud');
		$logger->log('strange', 'Odd level');
		$logger->info('Hidden');
		$logger->notice('Shown');

		self::assertStringContainsString('ERROR: Odd level', $this->log());
		self::assertStringNotContainsString('Hidden', $this->log());
		self::assertStringContainsString('NOTICE: Shown', $this->log());
	}

	public function testLogsExceptionWithTrace(): void
	{
		(new FileLogger($this->dir))->error('Failed', ['exception' => new RuntimeException('Boom')]);

		self::assertStringContainsString("ERROR: Failed\n  RuntimeException: Boom in " . __FILE__, $this->log());
		self::assertStringContainsString('#0 ', $this->log());
	}

	public function testRotatesLargeLog(): void
	{
		(new FileLogger($this->dir))->error('First');
		file_put_contents($this->dir . '/app.log', str_repeat('x', 10 * 1024 * 1024 + 1));
		(new FileLogger($this->dir))->error('Second');

		self::assertTrue(is_file($this->dir . '/app.log.1'));
		self::assertStringStartsWith('[', $this->log());
		self::assertStringContainsString('ERROR: Second', $this->log());
	}

	private function log(): string
	{
		return (string) file_get_contents($this->dir . '/' . FileLogger::FileName);
	}
}
