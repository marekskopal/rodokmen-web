<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Http\TrustedProxies;
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

	public function __construct(private Config $config, private TrustedProxies $proxies)
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
