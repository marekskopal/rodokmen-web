<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const LOCK_EX;
use const LOCK_SH;
use const LOCK_UN;

/**
 * File-based brute force protection. Every scope (the login form, the sync API) counts failures separately:
 * after MaxAttempts failures from one client within Window seconds, further attempts are refused until the window
 * passes. The login form also has a limit for all clients together, so that guesses spread over many addresses
 * are slowed down as well; it only blocks new logins, visitors who are logged in are not affected.
 */
final readonly class LoginThrottle
{
	private const MaxAttempts = 5;

	/** Failures of all clients together that block the scope; only the login form has such a limit. */
	private const GlobalLoginMaxAttempts = 50;
	private const Window = 900;

	public function __construct(private Config $config)
	{
	}

	/** Seconds the client has to wait, 0 when an attempt is allowed. */
	public function retryAfter(ServerRequestInterface $request, ThrottleScope $scope): int
	{
		$wait = self::wait($this->recentAttempts($this->file($request, $scope)), self::MaxAttempts);
		$global = $scope === ThrottleScope::Login ? self::GlobalLoginMaxAttempts : null;

		return $global === null ? $wait : max($wait, self::wait($this->recentAttempts($this->globalFile($scope)), $global));
	}

	public function registerFailure(ServerRequestInterface $request, ThrottleScope $scope): void
	{
		$this->append($this->file($request, $scope));
		if ($scope === ThrottleScope::Login) {
			$this->append($this->globalFile($scope));
		}
	}

	/** After a success the client starts again; the global count only expires with time. */
	public function reset(ServerRequestInterface $request, ThrottleScope $scope): void
	{
		@unlink($this->file($request, $scope));
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

	/** @param list<int> $attempts */
	private static function wait(array $attempts, int $max): int
	{
		if (count($attempts) < $max) {
			return 0;
		}

		// The oldest attempts that keep the count at the limit have to expire first.
		rsort($attempts);

		return max(1, $attempts[$max - 1] + self::Window - time());
	}

	/** @return list<int> */
	private function recentAttempts(string $file): array
	{
		$handle = is_file($file) ? fopen($file, 'r') : false;
		if ($handle === false) {
			return [];
		}

		// A shared lock, so that a file truncated by a parallel append() is not read as empty.
		flock($handle, LOCK_SH);
		$attempts = self::parseRecent((string) stream_get_contents($handle));
		flock($handle, LOCK_UN);
		fclose($handle);

		return $attempts;
	}

	/** @return list<int> */
	private static function parseRecent(string $json): array
	{
		$data = json_decode($json, true);
		$limit = time() - self::Window;

		return array_values(array_filter(is_array($data) ? $data : [], static fn (mixed $t): bool => is_int($t) && $t > $limit));
	}

	private function append(string $file): void
	{
		$dir = dirname($file);
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}

		$handle = fopen($file, 'c+');
		if ($handle === false) {
			return;
		}

		// Read, append and write under one lock, so that parallel failures are all counted.
		flock($handle, LOCK_EX);
		$attempts = self::parseRecent((string) stream_get_contents($handle));
		$attempts[] = time();
		ftruncate($handle, 0);
		rewind($handle);
		fwrite($handle, (string) json_encode($attempts));
		flock($handle, LOCK_UN);
		fclose($handle);
	}

	private function file(ServerRequestInterface $request, ThrottleScope $scope): string
	{
		return $this->config->cacheDir . '/throttle/' . $scope->value . '-' . hash('sha256', $this->clientIp($request)) . '.json';
	}

	private function globalFile(ThrottleScope $scope): string
	{
		return $this->config->cacheDir . '/throttle/' . $scope->value . '-all.json';
	}
}
