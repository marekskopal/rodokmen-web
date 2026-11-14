<?php

declare(strict_types=1);

namespace Rodokmen\Site\Http;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use const FILTER_VALIDATE_IP;

/**
 * The reverse proxies in front of the website (SITE_TRUSTED_PROXIES). Only a request that comes from one of them
 * may tell the client's address (X-Forwarded-For) and that the visitor used HTTPS (X-Forwarded-Proto); anybody
 * else could forge these headers.
 */
final readonly class TrustedProxies
{
	/** @var list<array{string, int}> the trusted ranges, parsed once: network address (binary) and prefix length */
	private array $networks;

	public function __construct(Config $config)
	{
		$networks = [];
		foreach ($config->trustedProxies as $range) {
			[$address, $bits] = self::splitRange($range);
			$network = $address !== null ? @inet_pton($address) : false;
			if ($network !== false && $bits !== null) {
				$networks[] = [$network, $bits];
			}
		}

		$this->networks = $networks;
	}

	/** Whether the request came through a trusted proxy, i.e. its forwarded headers can be believed. */
	public function isFromProxy(ServerRequestInterface $request): bool
	{
		$remote = $this->remoteAddress($request);

		return $remote !== null && $this->isTrusted($remote);
	}

	/**
	 * The client's address. X-Forwarded-For is read from the right: every trusted proxy appended the address it
	 * received the request from, so the first address that is not a trusted proxy is the client; everything to the
	 * left of it was sent by the client and can be forged. When all the entries are trusted proxies (a client on the
	 * private network), the rightmost one is used: the nearest proxy appended it, the client cannot forge it.
	 */
	public function clientIp(ServerRequestInterface $request): string
	{
		$remote = $this->remoteAddress($request);
		if ($remote === null) {
			return 'unknown';
		}

		if (!$this->isTrusted($remote)) {
			return $remote;
		}

		$nearest = null;
		foreach (array_reverse(explode(',', $request->getHeaderLine('X-Forwarded-For'))) as $hop) {
			$hop = self::normalize($hop);
			if ($hop === null) {
				// Garbage in the header: nothing to the left of it can be trusted.
				break;
			}

			if (!$this->isTrusted($hop)) {
				return $hop;
			}

			$nearest ??= $hop;
		}

		return $nearest ?? $remote;
	}

	/**
	 * First value of a forwarded header (X-Forwarded-Proto, X-Forwarded-Host), only when a trusted proxy sent the
	 * request; null otherwise. With chained proxies the first value is what the visitor used.
	 */
	public function forwarded(ServerRequestInterface $request, string $header): ?string
	{
		if (!$this->isFromProxy($request)) {
			return null;
		}

		$value = trim(explode(',', $request->getHeaderLine($header))[0]);

		return $value !== '' ? $value : null;
	}

	/** The host the visitor asked for: X-Forwarded-Host of a trusted proxy (without a port), else the request's host. */
	public function host(ServerRequestInterface $request): string
	{
		$forwarded = $this->forwarded($request, 'X-Forwarded-Host');
		if ($forwarded !== null && preg_match('~^(\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::\d+)?$~i', $forwarded, $m) === 1) {
			return strtolower($m[1]);
		}

		return $request->getUri()->getHost();
	}

	public function isTrusted(string $address): bool
	{
		$binary = @inet_pton($address);
		if ($binary === false) {
			return false;
		}

		foreach ($this->networks as [$network, $bits]) {
			if (self::inRange($binary, $network, $bits)) {
				return true;
			}
		}

		return false;
	}

	/** Whether the string is an address or a range (CIDR) that SITE_TRUSTED_PROXIES accepts. */
	public static function isValidRange(string $range): bool
	{
		[$address, $bits] = self::splitRange($range);
		$binary = $address !== null ? @inet_pton($address) : false;

		return $binary !== false && $bits !== null && $bits <= strlen($binary) * 8;
	}

	/**
	 * A valid IP address without a port, brackets or the IPv4-mapped IPv6 prefix, null for anything else.
	 */
	public static function normalize(string $address): ?string
	{
		$address = trim($address);
		if (preg_match('~^\[(.+)\](?::\d+)?$~', $address, $m) === 1) {
			$address = $m[1];
		} elseif (preg_match('~^(\d{1,3}(?:\.\d{1,3}){3}):\d+$~', $address, $m) === 1) {
			$address = $m[1];
		}

		if (filter_var($address, FILTER_VALIDATE_IP) === false) {
			return null;
		}

		if (preg_match('~^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$~i', $address, $m) === 1) {
			return $m[1];
		}

		return $address;
	}

	private function remoteAddress(ServerRequestInterface $request): ?string
	{
		$remote = $request->getServerParams()['REMOTE_ADDR'] ?? null;

		return is_string($remote) ? self::normalize($remote) : null;
	}

	private static function inRange(string $binary, string $network, int $bits): bool
	{
		if (strlen($network) !== strlen($binary)) {
			return false;
		}

		$bytes = intdiv($bits, 8);
		if (substr($binary, 0, $bytes) !== substr($network, 0, $bytes)) {
			return false;
		}

		$rest = $bits % 8;
		if ($rest === 0) {
			return true;
		}

		$mask = (0xff << 8 - $rest) & 0xff;

		return (ord($binary[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
	}

	/** @return array{?string, ?int} address and prefix length ("10.0.0.0/8"; a bare address is a single host) */
	private static function splitRange(string $range): array
	{
		$parts = explode('/', trim($range), 2);
		$address = $parts[0] !== '' ? $parts[0] : null;
		if (!isset($parts[1])) {
			$binary = $address !== null ? @inet_pton($address) : false;

			return [$address, $binary !== false ? strlen($binary) * 8 : null];
		}

		return [$address, preg_match('/^\d{1,3}$/', $parts[1]) === 1 ? (int) $parts[1] : null];
	}
}
