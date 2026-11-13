<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
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

	public function clientIp(ServerRequestInterface $request): string
	{
		$server = $request->getServerParams();
		if ($this->config->trustProxy && $request->hasHeader('X-Forwarded-For')) {
			return trim(explode(',', $request->getHeaderLine('X-Forwarded-For'))[0]);
		}

		$address = $server['REMOTE_ADDR'] ?? null;

		return is_string($address) ? $address : 'unknown';
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
