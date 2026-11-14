<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Auth;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Auth\ThrottleScope;
use Rodokmen\Site\Config;
use Rodokmen\Site\Tests\Support\Fixture;
use const PHP_BINARY;

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

		$throttle->release($request, ThrottleScope::Login);
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

	public function testReserveCountsTheAttemptBeforeItIsChecked(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		$request = $this->request('10.0.0.6');

		for ($i = 0; $i < 5; $i++) {
			self::assertSame(0, $throttle->reserve($request, ThrottleScope::Login), 'attempt ' . ($i + 1));
		}
		self::assertGreaterThan(800, $throttle->reserve($request, ThrottleScope::Login));
		self::assertGreaterThan(800, $throttle->retryAfter($request, ThrottleScope::Login));

		// Refused attempts are not counted: after a success the client starts again.
		$throttle->release($request, ThrottleScope::Login);
		self::assertSame(0, $throttle->reserve($request, ThrottleScope::Login));
	}

	public function testReleaseTakesTheAttemptBackFromTheGlobalCount(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 0; $i < 49; $i++) {
			$throttle->registerFailure($this->request('198.51.100.' . $i), ThrottleScope::Login);
		}

		// A successful login does not use up the last free place of all clients…
		$visitor = $this->request('203.0.113.7');
		self::assertSame(0, $throttle->reserve($visitor, ThrottleScope::Login));
		$throttle->release($visitor, ThrottleScope::Login);
		self::assertSame(0, $throttle->retryAfter($this->request('203.0.113.8'), ThrottleScope::Login));

		// …a failed one does.
		self::assertSame(0, $throttle->reserve($visitor, ThrottleScope::Login));
		self::assertGreaterThan(0, $throttle->retryAfter($this->request('203.0.113.8'), ThrottleScope::Login));
	}

	public function testParallelAttemptsCannotPassTheLimit(): void
	{
		// 20 processes try to log in at the same moment from one address: only five may go on.
		$script = sprintf(
			<<<'PHP'
				require %s;
				$throttle = Rodokmen\Site\Tests\Support\Fixture::throttle(Rodokmen\Site\Tests\Support\Fixture::config(%s));
				// A slow start must not fail on a start time already passed: the limit holds even then.
				$start = %F;
				if ($start > microtime(true)) {
					time_sleep_until($start);
				}
				echo $throttle->reserve(new Laminas\Diactoros\ServerRequest(['REMOTE_ADDR' => '10.0.0.7']), Rodokmen\Site\Auth\ThrottleScope::Login) === 0 ? 1 : 0;
				PHP,
			var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
			var_export($this->dir, true),
			microtime(true) + 1.0,
		);

		$processes = [];
		for ($i = 0; $i < 20; $i++) {
			$process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			self::assertIsResource($process);
			$processes[] = [$process, $pipes];
		}

		$allowed = 0;
		foreach ($processes as [$process, $pipes]) {
			$allowed += (int) stream_get_contents($pipes[1]);
			self::assertSame('', stream_get_contents($pipes[2]));
			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($process);
		}

		self::assertSame(5, $allowed);
	}

	public function testKnownDeviceIsNotStoppedByTheGlobalLimit(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 0; $i < 50; $i++) {
			$throttle->registerFailure($this->request('198.51.100.' . $i), ThrottleScope::Login);
		}

		$family = $this->request('203.0.113.7');
		self::assertGreaterThan(0, $throttle->reserve($family, ThrottleScope::Login), 'a new browser waits');
		self::assertSame(0, $throttle->reserve($family, ThrottleScope::Login, device: 'device-1'), 'a known one may log in');
		$throttle->release($family, ThrottleScope::Login, device: 'device-1');
	}

	public function testKnownDeviceStillHasItsOwnLimitAndDoesNotFillTheGlobalOne(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		$device = $this->request('203.0.113.9');
		for ($i = 0; $i < 5; $i++) {
			self::assertSame(0, $throttle->reserve($device, ThrottleScope::Login, device: 'device-1'));
		}

		self::assertGreaterThan(0, $throttle->reserve($device, ThrottleScope::Login, device: 'device-1'));
		self::assertFileDoesNotExist($this->dir . '/cache/throttle/login-all.json', 'not counted for all clients');
	}

	public function testKnownDeviceIsLimitedAcrossAddresses(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 0; $i < 5; $i++) {
			self::assertSame(0, $throttle->reserve($this->request('198.51.100.' . $i), ThrottleScope::Login, device: 'stolen'));
		}

		self::assertGreaterThan(
			0,
			$throttle->reserve($this->request('198.51.100.99'), ThrottleScope::Login, device: 'stolen'),
			'one device, many addresses',
		);
		self::assertSame(0, $throttle->reserve($this->request('198.51.100.99'), ThrottleScope::Login, device: 'another'));
	}

	public function testIpv4MappedClientsAreCountedPerAddress(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 0; $i < 5; $i++) {
			$throttle->registerFailure($this->request('::ffff:c000:201'), ThrottleScope::Login);
		}

		self::assertGreaterThan(0, $throttle->retryAfter($this->request('192.0.2.1'), ThrottleScope::Login), 'the same IPv4 client');
		self::assertSame(0, $throttle->retryAfter($this->request('::ffff:c000:202'), ThrottleScope::Login), 'another IPv4 client');
	}

	public function testIpv6ClientsAreCountedPerNetwork(): void
	{
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 1; $i <= 5; $i++) {
			$throttle->registerFailure($this->request('2001:db8:1:2::' . dechex($i)), ThrottleScope::Login);
		}

		self::assertGreaterThan(0, $throttle->retryAfter($this->request('2001:db8:1:2:ffff::1'), ThrottleScope::Login), 'the same /64');
		self::assertSame(0, $throttle->retryAfter($this->request('2001:db8:1:3::1'), ThrottleScope::Login), 'another /64');
		self::assertSame(0, $throttle->retryAfter($this->request('192.0.2.1'), ThrottleScope::Login));
	}

	private function request(string $ip): ServerRequest
	{
		return new ServerRequest(['REMOTE_ADDR' => $ip]);
	}
}
