<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Logger;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Logger\ErrorStream;
use const PHP_EOL;

final class ErrorStreamTest extends TestCase
{
	public function testStderrUnderFastCgiGoesThroughTheWebServersLogger(): void
	{
		self::assertTrue(ErrorStream::viaSapi(ErrorStream::Stderr, 'fpm-fcgi'));
		self::assertTrue(ErrorStream::viaSapi(ErrorStream::Stderr, 'cgi-fcgi'));
	}

	public function testModPhpCommandLineAndOtherStreamsAreWrittenDirectly(): void
	{
		self::assertFalse(ErrorStream::viaSapi(ErrorStream::Stderr, 'apache2handler'));
		self::assertFalse(ErrorStream::viaSapi(ErrorStream::Stderr, 'cli'));
		self::assertFalse(ErrorStream::viaSapi(ErrorStream::Stderr, 'cli-server'));
		self::assertFalse(ErrorStream::viaSapi('/tmp/rodokmen-stderr', 'fpm-fcgi'));
	}

	public function testWritesALineToAStream(): void
	{
		$stream = (string) tempnam(sys_get_temp_dir(), 'rodokmen-stderr-');

		try {
			ErrorStream::write($stream, 'Boom');

			self::assertSame('Boom' . PHP_EOL, file_get_contents($stream));
		} finally {
			unlink($stream);
		}
	}
}
