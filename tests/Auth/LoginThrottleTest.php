<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Auth;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Auth\LoginThrottle;
use Rodokmen\Site\Tests\Support\Fixture;

final class LoginThrottleTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testBlocksAfterFiveFailures(): void
	{
		$throttle = new LoginThrottle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.1');

		for ($i = 0; $i < 4; $i++) {
			$throttle->registerFailure($request);
		}
		self::assertSame(0, $throttle->retryAfter($request));

		$throttle->registerFailure($request);
		self::assertGreaterThan(800, $throttle->retryAfter($request));
		self::assertSame(0, $throttle->retryAfter($this->request('10.0.0.2')), 'other clients are not affected');

		$throttle->reset($request);
		self::assertSame(0, $throttle->retryAfter($request));
	}

	public function testOldAttemptsExpire(): void
	{
		$throttle = new LoginThrottle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.3');
		$throttle->registerFailure($request);

		$files = glob($this->dir . '/cache/throttle/*.json');
		self::assertIsArray($files);
		self::assertCount(1, $files);
		file_put_contents($files[0], json_encode([time() - 1000, time() - 950, time() - 901, time() - 2000, 'x']));

		$throttle->registerFailure($request);
		self::assertSame(0, $throttle->retryAfter($request));
	}

	public function testClientIp(): void
	{
		$request = $this->request('10.0.0.1')->withHeader('X-Forwarded-For', '203.0.113.5, 10.0.0.1');

		self::assertSame('10.0.0.1', new LoginThrottle(Fixture::config($this->dir))->clientIp($request));
		self::assertSame('203.0.113.5', new LoginThrottle(Fixture::config($this->dir, trustProxy: true))->clientIp($request));
		self::assertSame('unknown', new LoginThrottle(Fixture::config($this->dir))->clientIp(new ServerRequest()));
	}

	private function request(string $ip): ServerRequest
	{
		return new ServerRequest(['REMOTE_ADDR' => $ip]);
	}
}
