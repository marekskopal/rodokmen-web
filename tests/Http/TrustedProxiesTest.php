<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Http;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Config;
use Rodokmen\Site\Http\TrustedProxies;
use Rodokmen\Site\Tests\Support\Fixture;
use function sys_get_temp_dir;

final class TrustedProxiesTest extends TestCase
{
	/** @return iterable<string, array{string, bool}> */
	public static function addresses(): iterable
	{
		yield 'IPv4 range' => ['203.0.113.77', true];
		yield 'IPv4 outside' => ['203.0.114.1', false];
		yield 'single host' => ['198.51.100.7', true];
		yield 'next host' => ['198.51.100.8', false];
		yield 'prefix not on a byte boundary' => ['100.127.255.255', true];
		yield 'just outside it' => ['100.128.0.0', false];
		yield 'IPv6 range' => ['2001:db8:1::5', true];
		yield 'IPv6 outside' => ['2001:db9::1', false];
		yield 'IPv4 is not IPv6' => ['::ffff:203.0.113.77', false];
		yield 'garbage' => ['proxy', false];
	}

	#[DataProvider('addresses')]
	public function testIsTrusted(string $address, bool $trusted): void
	{
		self::assertSame(
			$trusted,
			$this->proxies(['203.0.113.0/24', '198.51.100.7', '100.64.0.0/10', '2001:db8::/32'])->isTrusted($address),
		);
	}

	public function testValidRanges(): void
	{
		foreach (['10.0.0.0/8', '10.1.2.3', '::1', 'fc00::/7', '0.0.0.0/0', '2001:db8::/128'] as $range) {
			self::assertTrue(TrustedProxies::isValidRange($range), $range);
		}
		foreach (['10.0.0.0/33', '::1/129', '10.0.0/8', '10.0.0.0/', '/8', 'example.org', '10.0.0.0/x', ''] as $range) {
			self::assertFalse(TrustedProxies::isValidRange($range), $range);
		}
	}

	public function testPublicProxyIsSupported(): void
	{
		// e.g. a CDN or a hosting provider's load balancer at a public address
		$proxies = $this->proxies(['198.51.100.0/24']);
		$request = new ServerRequest(['REMOTE_ADDR' => '198.51.100.20'])->withHeader('X-Forwarded-For', '192.0.2.99, 203.0.113.5');

		self::assertTrue($proxies->isFromProxy($request));
		self::assertSame('203.0.113.5', $proxies->clientIp($request));
	}

	public function testClientOnThePrivateNetworkIsNotMistakenForAProxy(): void
	{
		// Only the proxy at 10.0.0.1 is trusted: a LAN client's forged header is ignored.
		$proxies = $this->proxies(['10.0.0.1']);
		$request = new ServerRequest(['REMOTE_ADDR' => '10.0.0.1'])->withHeader('X-Forwarded-For', '10.99.99.1, 10.0.0.50');
		self::assertSame('10.0.0.50', $proxies->clientIp($request));

		$direct = new ServerRequest(['REMOTE_ADDR' => '10.0.0.50'])->withHeader('X-Forwarded-For', '10.99.99.1');
		self::assertFalse($proxies->isFromProxy($direct));
		self::assertSame('10.0.0.50', $proxies->clientIp($direct));
	}

	public function testNothingIsTrustedByDefault(): void
	{
		$proxies = $this->proxies([]);
		$request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'])->withHeader('X-Forwarded-For', '203.0.113.5');

		self::assertFalse($proxies->isFromProxy($request));
		self::assertSame('127.0.0.1', $proxies->clientIp($request));
	}

	public function testPrivateNetworks(): void
	{
		$proxies = $this->proxies(Config::PrivateNetworks);

		foreach (['127.0.0.1', '10.1.2.3', '172.18.0.2', '192.168.1.1', '::1', 'fd00::1'] as $address) {
			self::assertTrue($proxies->isTrusted($address), $address);
		}
		foreach (['203.0.113.5', '172.32.0.1', '100.64.0.1', '2001:db8::1'] as $address) {
			self::assertFalse($proxies->isTrusted($address), $address);
		}
	}

	/** @param list<string> $ranges */
	private function proxies(array $ranges): TrustedProxies
	{
		return new TrustedProxies(Fixture::config(sys_get_temp_dir(), trustedProxies: $ranges));
	}
}
