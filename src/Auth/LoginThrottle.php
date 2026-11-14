<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Http\TrustedProxies;

/**
 * File-based brute force protection. Every scope (the login form, the sync API) counts failures separately:
 * after MaxAttempts failures from one client (an address, an IPv6 /64 network) within Window seconds, further
 * attempts are refused until the window passes. The login form also has a limit for all clients together, so that
 * guesses spread over many addresses are slowed down as well; it only blocks new browsers. A browser that has logged
 * in before (Auth::knownDevice) gets a limit of its own instead, so that such an attack cannot lock the family out.
 *
 * The login form checks and counts an attempt in one locked step (reserve(), then release() after a success), so
 * a burst of parallel guesses cannot all pass the check before any of them is counted. The sync API counts only
 * failures (retryAfter(), registerFailure()): a sync sends several requests in parallel with the same token, and
 * its random token cannot be guessed in a handful of tries anyway.
 */
final readonly class LoginThrottle
{
	private const MaxAttempts = 5;

	/** Failures of all clients together that block the scope; only the login form has such a limit. */
	private const GlobalLoginMaxAttempts = 50;
	private const Window = 900;

	public function __construct(private Config $config, private TrustedProxies $proxies)
	{
	}

	/** Seconds the client has to wait, 0 when an attempt is allowed. */
	public function retryAfter(ServerRequestInterface $request, ThrottleScope $scope): int
	{
		$wait = self::wait(ThrottleFile::peek($this->file($request, $scope), self::Window), self::MaxAttempts);
		$global = $scope === ThrottleScope::Login ? self::GlobalLoginMaxAttempts : null;

		return $global === null ? $wait : max($wait, self::wait(ThrottleFile::peek($this->globalFile($scope), self::Window), $global));
	}

	public function registerFailure(ServerRequestInterface $request, ThrottleScope $scope): void
	{
		$this->append($this->file($request, $scope));
		if ($scope === ThrottleScope::Login) {
			$this->append($this->globalFile($scope));
		}
	}

	/**
	 * Checks the limits and counts the attempt as a failure in one locked step. Returns the seconds the client has to
	 * wait (the attempt is refused and not counted), or 0 when the attempt may go on – then call release() if it
	 * succeeds. A known device (Auth::knownDevice) is limited per device instead of by the limit for all clients.
	 */
	public function reserve(ServerRequestInterface $request, ThrottleScope $scope, ?string $device = null): int
	{
		$sharedMax = $device !== null ? self::MaxAttempts : self::GlobalLoginMaxAttempts;

		return $this->locked(
			$request,
			$scope,
			$device,
			static function (?ThrottleFile $client, ?ThrottleFile $shared) use ($sharedMax): int {
				$clientAttempts = $client?->read() ?? [];
				$sharedAttempts = $shared?->read() ?? [];
				$wait = max(
					self::wait($clientAttempts, self::MaxAttempts),
					$shared !== null ? self::wait($sharedAttempts, $sharedMax) : 0,
				);
				if ($wait > 0) {
					return $wait;
				}

				$now = time();
				$client?->write([...$clientAttempts, $now]);
				$shared?->write([...$sharedAttempts, $now]);

				return 0;
			},
		);
	}

	/** The reserved attempt succeeded: the client and the device start again, and the attempt no longer counts for all clients. */
	public function release(ServerRequestInterface $request, ThrottleScope $scope, ?string $device = null): void
	{
		$this->locked($request, $scope, $device, static function (?ThrottleFile $client, ?ThrottleFile $shared) use ($device): void {
			$client?->write([]);

			if ($shared === null) {
				return;
			}

			if ($device !== null) {
				$shared->write([]);

				return;
			}

			// Attempts are not told apart: dropping the newest one keeps the count right.
			$attempts = $shared->read();
			sort($attempts);
			array_pop($attempts);
			$shared->write($attempts);
		});
	}

	/** The client's address, as far as the trusted proxies tell it (SITE_TRUSTED_PROXIES). */
	public function clientIp(ServerRequestInterface $request): string
	{
		return $this->proxies->clientIp($request);
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

	private function append(string $file): void
	{
		$handle = ThrottleFile::open($file, self::Window);
		if ($handle === null) {
			return;
		}

		// Read, append and write under one lock, so that parallel failures are all counted.
		try {
			$handle->write([...$handle->read(), time()]);
		} finally {
			$handle->close();
		}
	}

	/**
	 * Runs the action with the client's file and, for the login form, the shared file locked: the device's file for
	 * a known device (Auth::knownDevice), which is neither checked against nor counted in the limit for all clients,
	 * else the global file. The lock order is always the same (the client's file first), so that parallel requests
	 * cannot wait for each other forever. A file that cannot be written is null: the throttle then does not count
	 * instead of failing the request.
	 *
	 * @param Closure(?ThrottleFile, ?ThrottleFile): T $action
	 * @return T
	 * @template T
	 */
	private function locked(ServerRequestInterface $request, ThrottleScope $scope, ?string $device, Closure $action): mixed
	{
		$client = ThrottleFile::open($this->file($request, $scope), self::Window);
		$shared = null;
		if ($scope === ThrottleScope::Login) {
			$shared = ThrottleFile::open(
				$device !== null ? $this->deviceFile($scope, $device) : $this->globalFile($scope),
				self::Window,
			);
		}

		try {
			return $action($client, $shared);
		} finally {
			$shared?->close();
			$client?->close();
		}
	}

	private function file(ServerRequestInterface $request, ThrottleScope $scope): string
	{
		return $this->config->cacheDir . '/throttle/' . $scope->value . '-' . hash(
			'sha256',
			self::bucket($this->clientIp($request)),
		) . '.json';
	}

	/**
	 * What one client is: its address, for IPv6 its /64 network – a single connection usually gets a whole /64,
	 * so counting addresses one by one would let it try again from a fresh address every time.
	 */
	private static function bucket(string $address): string
	{
		$binary = str_contains($address, ':') ? @inet_pton($address) : false;
		if ($binary === false || strlen($binary) !== 16) {
			return $address;
		}

		// An IPv4-mapped address (::ffff:a.b.c.d in any spelling) is one IPv4 client, not a share of the ::/64.
		if (substr($binary, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
			return (string) inet_ntop(substr($binary, 12));
		}

		return bin2hex(substr($binary, 0, 8)) . '::/64';
	}

	private function deviceFile(ThrottleScope $scope, string $device): string
	{
		return $this->config->cacheDir . '/throttle/' . $scope->value . '-device-' . hash('sha256', $device) . '.json';
	}

	private function globalFile(ThrottleScope $scope): string
	{
		return $this->config->cacheDir . '/throttle/' . $scope->value . '-all.json';
	}
}
