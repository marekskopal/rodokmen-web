<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Auth;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Auth\ThrottleScope;
use Rodokmen\Site\Config;
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
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.1');

		for ($i = 0; $i < 4; $i++) {
			$throttle->registerFailure($request, ThrottleScope::Login);
		}
		self::assertSame(0, $throttle->retryAfter($request, ThrottleScope::Login));

		$throttle->registerFailure($request, ThrottleScope::Login);
		self::assertGreaterThan(800, $throttle->retryAfter($request, ThrottleScope::Login));
		self::assertSame(0, $throttle->retryAfter($this->request('10.0.0.2'), ThrottleScope::Login), 'other clients are not affected');

		$throttle->reset($request, ThrottleScope::Login);
		self::assertSame(0, $throttle->retryAfter($request, ThrottleScope::Login));
	}

	public function testOldAttemptsExpire(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.3');
		$throttle->registerFailure($request, ThrottleScope::Login);

		$files = glob($this->dir . '/cache/throttle/login-*.json');
		self::assertIsArray($files);
		self::assertCount(2, $files, 'the client and all clients');
		foreach ($files as $file) {
			file_put_contents($file, json_encode([time() - 1000, time() - 950, time() - 901, time() - 2000, 'x']));
		}

		$throttle->registerFailure($request, ThrottleScope::Login);
		self::assertSame(0, $throttle->retryAfter($request, ThrottleScope::Login));
	}

	public function testClientIp(): void
	{
		$request = $this->request('10.0.0.1')->withHeader('X-Forwarded-For', '203.0.113.5, 10.0.0.1');

		self::assertSame('10.0.0.1', Fixture::throttle(Fixture::config($this->dir))->clientIp($request));
		self::assertSame(
			'203.0.113.5',
			Fixture::throttle(Fixture::config($this->dir, trustedProxies: Config::PrivateNetworks))->clientIp($request),
		);
		self::assertSame('unknown', Fixture::throttle(Fixture::config($this->dir))->clientIp(new ServerRequest()));
	}

	public function testForgedForwardedForIsIgnored(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir, trustedProxies: Config::PrivateNetworks));

		// Proxy without mod_remoteip: it appended the real client, the client forged the entries to the left.
		$request = $this->request('172.18.0.2')->withHeader('X-Forwarded-For', '198.51.100.66, 203.0.113.5');
		self::assertSame('203.0.113.5', $throttle->clientIp($request));

		// Several proxies (e.g. a load balancer and Traefik): internal hops are skipped.
		$request = $this->request('127.0.0.1')->withHeader('X-Forwarded-For', '198.51.100.66, 203.0.113.5, 10.1.2.3');
		self::assertSame('203.0.113.5', $throttle->clientIp($request));

		// Docker image: mod_remoteip already put the client into REMOTE_ADDR and left only the forged part.
		$request = $this->request('203.0.113.5')->withHeader('X-Forwarded-For', '198.51.100.66');
		self::assertSame('203.0.113.5', $throttle->clientIp($request));

		// A malformed entry stops the walk at the last trustworthy hop.
		$request = $this->request('10.0.0.1')->withHeader('X-Forwarded-For', '198.51.100.66, junk, 10.0.0.9');
		self::assertSame('10.0.0.9', $throttle->clientIp($request));

		// No header behind a proxy: the proxy itself.
		self::assertSame('10.0.0.1', $throttle->clientIp($this->request('10.0.0.1')));
	}

	public function testClientOnThePrivateNetworkCannotForgeItsAddress(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir, trustedProxies: Config::PrivateNetworks));

		// Every entry is private: the client forged the left one, the proxy appended the right one.
		$request = $this->request('10.0.0.1')->withHeader('X-Forwarded-For', '10.99.99.7, 192.168.1.20');
		self::assertSame('192.168.1.20', $throttle->clientIp($request));
	}

	public function testClientIpNormalizesAddresses(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir, trustedProxies: Config::PrivateNetworks));

		// An IPv4-mapped public address is a direct client, not a proxy: its header is not believed.
		$request = $this->request('::ffff:203.0.113.5')->withHeader('X-Forwarded-For', '198.51.100.66');
		self::assertSame('203.0.113.5', $throttle->clientIp($request));

		// Proxies that append the port (IIS ARR, Azure) or bracket IPv6 addresses.
		$request = $this->request('10.0.0.1')->withHeader('X-Forwarded-For', '198.51.100.66, 203.0.113.5:51234');
		self::assertSame('203.0.113.5', $throttle->clientIp($request));
		$request = $this->request('10.0.0.1')->withHeader('X-Forwarded-For', '[2001:db8::5]:443');
		self::assertSame('2001:db8::5', $throttle->clientIp($request));
	}

	public function testForgedAddressesDoNotEscapeTheThrottle(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir, trustedProxies: Config::PrivateNetworks));
		for ($i = 0; $i < 5; $i++) {
			$throttle->registerFailure(
				$this->request('10.0.0.1')->withHeader('X-Forwarded-For', '198.51.100.' . $i . ', 203.0.113.5'),
				ThrottleScope::Login,
			);
		}

		self::assertGreaterThan(
			0,
			$throttle->retryAfter(
				$this->request('10.0.0.1')->withHeader('X-Forwarded-For', '198.51.100.99, 203.0.113.5'),
				ThrottleScope::Login,
			),
		);
	}

	public function testScopesAreCountedSeparately(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.4');
		for ($i = 0; $i < 5; $i++) {
			$throttle->registerFailure($request, ThrottleScope::Sync);
		}

		self::assertGreaterThan(0, $throttle->retryAfter($request, ThrottleScope::Sync));
		self::assertSame(0, $throttle->retryAfter($request, ThrottleScope::Login), 'wrong sync tokens do not block the login form');
	}

	public function testGlobalLoginLimit(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 0; $i < 50; $i++) {
			$request = $this->request('198.51.100.' . $i);
			self::assertSame(0, $throttle->retryAfter($request, ThrottleScope::Login));
			$throttle->registerFailure($request, ThrottleScope::Login);
		}

		self::assertGreaterThan(
			800,
			$throttle->retryAfter($this->request('198.51.100.200'), ThrottleScope::Login),
			'guesses from many addresses',
		);
		self::assertSame(
			0,
			$throttle->retryAfter($this->request('198.51.100.200'), ThrottleScope::Sync),
			'the sync API has no global limit',
		);
	}

	public function testWaitEndsWhenEnoughAttemptsExpire(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.5');
		$throttle->registerFailure($request, ThrottleScope::Sync);

		$files = glob($this->dir . '/cache/throttle/sync-*.json');
		self::assertIsArray($files);
		self::assertCount(1, $files);
		$now = time();
		file_put_contents($files[0], json_encode([$now - 800, $now - 600, $now - 400, $now - 200, $now - 100, $now]));

		// Six attempts: the wait ends when the second oldest expires and the count drops below five.
		$wait = $throttle->retryAfter($request, ThrottleScope::Sync);
		self::assertGreaterThanOrEqual(299, $wait);
		self::assertLessThanOrEqual(300, $wait);
	}

	private function request(string $ip): ServerRequest
	{
		return new ServerRequest(['REMOTE_ADDR' => $ip]);
	}
}
