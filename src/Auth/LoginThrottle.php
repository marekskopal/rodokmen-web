<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Http\TrustedProxies;

/**
 * File-based brute force protection. Every scope (the login form, the sync API) counts failures separately:
 * after MaxAttempts failures from one client within Window seconds, further attempts are refused until the window
 * passes. The login form also has a limit for all clients together, so that guesses spread over many addresses
 * are slowed down as well; it only blocks new logins, visitors who are logged in are not affected.
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
	 * succeeds.
	 */
	public function reserve(ServerRequestInterface $request, ThrottleScope $scope): int
	{
		return $this->locked($request, $scope, static function (?ThrottleFile $client, ?ThrottleFile $global): int {
			$clientAttempts = $client?->read() ?? [];
			$globalAttempts = $global?->read() ?? [];
			$wait = max(
				self::wait($clientAttempts, self::MaxAttempts),
				$global !== null ? self::wait($globalAttempts, self::GlobalLoginMaxAttempts) : 0,
			);
			if ($wait > 0) {
				return $wait;
			}

			$now = time();
			$client?->write([...$clientAttempts, $now]);
			$global?->write([...$globalAttempts, $now]);

			return 0;
		});
	}

	/** The reserved attempt succeeded: the client starts again, and its attempt no longer counts for all clients. */
	public function release(ServerRequestInterface $request, ThrottleScope $scope): void
	{
		$this->locked($request, $scope, static function (?ThrottleFile $client, ?ThrottleFile $global): void {
			$client?->write([]);

			if ($global === null) {
				return;
			}

			// Attempts are not told apart: dropping the newest one keeps the count right.
			$attempts = $global->read();
			sort($attempts);
			array_pop($attempts);
			$global->write($attempts);
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
	 * Runs the action with the client's file and, for the login form, the global file locked. The lock order is
	 * always the same (the client's file first), so that parallel requests cannot wait for each other forever.
	 * A file that cannot be written is null: the throttle then does not count instead of failing the request.
	 *
	 * @param Closure(?ThrottleFile, ?ThrottleFile): T $action
	 * @return T
	 * @template T
	 */
	private function locked(ServerRequestInterface $request, ThrottleScope $scope, Closure $action): mixed
	{
		$client = ThrottleFile::open($this->file($request, $scope), self::Window);
		$global = $scope === ThrottleScope::Login ? ThrottleFile::open($this->globalFile($scope), self::Window) : null;

		try {
			return $action($client, $global);
		} finally {
			$global?->close();
			$client?->close();
		}
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
