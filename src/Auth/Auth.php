<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Http\TrustedProxies;

/**
 * Password-only login. The login state is a signed cookie "expiry.signature", so it survives
 * container restarts and is invalidated automatically when the password hash or secret changes.
 */
final readonly class Auth
{
	public const CookieName = 'rk_auth';

	/**
	 * Marks a browser that has logged in before ("issued.signature"). It survives logout and a password change and
	 * grants nothing by itself: it only exempts the browser from the login limit for all clients (LoginThrottle), so
	 * that an attack from many addresses cannot lock the family out.
	 */
	public const DeviceCookieName = 'rk_device';

	private const DeviceDays = 365;

	public function __construct(private Config $config, private TrustedProxies $proxies)
	{
	}

	public function isConfigured(): bool
	{
		return $this->config->passwordHash !== '' && strlen($this->config->secret) >= 32;
	}

	public function verifyPassword(string $password): bool
	{
		return $this->isConfigured() && password_verify($password, $this->config->passwordHash);
	}

	public function isLoggedIn(ServerRequestInterface $request): bool
	{
		if (!$this->isConfigured()) {
			return false;
		}

		$token = self::signedCookie($request, self::CookieName);

		return $token !== null
			&& (int) $token[0] > time()
			&& hash_equals($this->sign($token[0]), $token[1]);
	}

	/** Returns the Set-Cookie header value for a fresh login. */
	public function loginCookie(ServerRequestInterface $request): string
	{
		$expires = (string) (time() + $this->config->loginDays * 86400);

		return $this->cookie(self::CookieName, $expires . '.' . $this->sign($expires), (int) $expires, $request);
	}

	public function logoutCookie(ServerRequestInterface $request): string
	{
		return $this->cookie(self::CookieName, '', 1, $request);
	}

	/** Whether the browser has logged in on this website before (a valid device cookie). */
	public function isKnownDevice(ServerRequestInterface $request): bool
	{
		return $this->knownDevice($request) !== null;
	}

	/**
	 * The valid device cookie of a browser that has logged in before, null for any other browser. LoginThrottle
	 * keeps a limit per device, so that one device cookie cannot be used to guess from many addresses.
	 */
	public function knownDevice(ServerRequestInterface $request): ?string
	{
		if (!$this->isConfigured()) {
			return null;
		}

		$token = self::signedCookie($request, self::DeviceCookieName);
		$now = time();

		return $token !== null
			&& (int) $token[0] + self::DeviceDays * 86400 > $now
			&& (int) $token[0] <= $now + 60
			&& hash_equals($this->signDevice($token[0]), $token[1])
			? $token[0] . '.' . $token[1]
			: null;
	}

	/** Set-Cookie header value marking the browser as known (sent with every successful login). */
	public function deviceCookie(ServerRequestInterface $request): string
	{
		$now = time();
		$issued = (string) $now;

		return $this->cookie(
			self::DeviceCookieName,
			$issued . '.' . $this->signDevice($issued),
			$now + self::DeviceDays * 86400,
			$request,
			'/prihlaseni',
		);
	}

	public function isHttps(ServerRequestInterface $request): bool
	{
		if ($request->getUri()->getScheme() === 'https') {
			return true;
		}

		// Only a trusted proxy may say that the visitor used HTTPS; anybody else could send the header.
		return strtolower($this->proxies->forwarded($request, 'X-Forwarded-Proto') ?? '') === 'https';
	}

	/** @return array{string, string}|null the time and the signature of a cookie "time.signature" */
	private static function signedCookie(ServerRequestInterface $request, string $name): ?array
	{
		$token = $request->getCookieParams()[$name] ?? '';
		if (!is_string($token) || !str_contains($token, '.')) {
			return null;
		}

		[$time, $signature] = explode('.', $token, 2);

		return ctype_digit($time) ? [$time, $signature] : null;
	}

	private function sign(string $expires): string
	{
		// The password hash is part of the key: changing the password logs everybody out.
		return hash_hmac('sha256', $expires, $this->config->secret . '|' . $this->config->passwordHash);
	}

	/** Only the secret is the key: unlike the login, a known device stays known after a password change. */
	private function signDevice(string $issued): string
	{
		return hash_hmac('sha256', 'device|' . $issued, $this->config->secret);
	}

	private function cookie(string $name, string $value, int $expires, ServerRequestInterface $request, string $path = '/'): string
	{
		$parts = [
			$name . '=' . rawurlencode($value),
			'Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT',
			'Path=' . $path,
			'HttpOnly',
			'SameSite=Lax',
		];
		if ($this->isHttps($request)) {
			$parts[] = 'Secure';
		}

		return implode('; ', $parts);
	}
}
