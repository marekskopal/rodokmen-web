<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const LOCK_EX;

/**
 * File-based brute force protection: after MaxAttempts failed logins from one client
 * within Window seconds, further attempts are refused until the window passes.
 */
final readonly class LoginThrottle
{
	private const MaxAttempts = 5;
	private const Window = 900;

	public function __construct(private Config $config)
	{
	}

	/** Seconds the client has to wait, 0 when a login attempt is allowed. */
	public function retryAfter(ServerRequestInterface $request): int
	{
		$attempts = $this->recentAttempts($request);
		if (count($attempts) < self::MaxAttempts) {
			return 0;
		}

		return max(1, min($attempts) + self::Window - time());
	}

	public function registerFailure(ServerRequestInterface $request): void
	{
		$attempts = $this->recentAttempts($request);
		$attempts[] = time();
		$this->write($request, $attempts);
	}

	public function reset(ServerRequestInterface $request): void
	{
		@unlink($this->file($request));
	}

	/**
	 * The client's address. Behind a trusted proxy (SITE_TRUST_PROXY) only the entries of X-Forwarded-For that
	 * proxies appended are believed: the header is read from the right and the first public address wins, because
	 * everything to the left of it was sent by the client and can be forged. When no entry is public (a client on
	 * the private network), the rightmost entry is used: the nearest proxy appended it, the client cannot forge it.
	 * When REMOTE_ADDR is already a public address (Apache's mod_remoteip in the Docker image has resolved the
	 * proxy), it is used as it is.
	 */
	public function clientIp(ServerRequestInterface $request): string
	{
		$remote = $request->getServerParams()['REMOTE_ADDR'] ?? null;
		$remote = is_string($remote) ? self::normalize($remote) : null;
		if ($remote === null) {
			return 'unknown';
		}

		if (!$this->config->trustProxy || !self::isProxy($remote)) {
			return $remote;
		}

		$nearest = null;
		foreach (array_reverse(explode(',', $request->getHeaderLine('X-Forwarded-For'))) as $hop) {
			$hop = self::normalize($hop);
			if ($hop === null) {
				// Garbage in the header: nothing to the left of it can be trusted.
				break;
			}

			if (!self::isProxy($hop)) {
				return $hop;
			}

			$nearest ??= $hop;
		}

		return $nearest ?? $remote;
	}

	/**
	 * A valid IP address without a port, brackets or the IPv4-mapped IPv6 prefix, null for anything else.
	 */
	private static function normalize(string $address): ?string
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

	/** Private, loopback and reserved addresses belong to the proxies in front of the website. */
	private static function isProxy(string $address): bool
	{
		return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
	}

	/** @return list<int> */
	private function recentAttempts(ServerRequestInterface $request): array
	{
		$file = $this->file($request);
		if (!is_file($file)) {
			return [];
		}

		$data = json_decode((string) file_get_contents($file), true);
		$limit = time() - self::Window;

		return array_values(array_filter(is_array($data) ? $data : [], static fn (mixed $t): bool => is_int($t) && $t > $limit));
	}

	/** @param list<int> $attempts */
	private function write(ServerRequestInterface $request, array $attempts): void
	{
		$dir = dirname($this->file($request));
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}

		file_put_contents($this->file($request), json_encode($attempts), LOCK_EX);
	}

	private function file(ServerRequestInterface $request): string
	{
		return $this->config->cacheDir . '/throttle/' . hash('sha256', $this->clientIp($request)) . '.json';
	}
}
