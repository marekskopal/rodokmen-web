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

		$token = $request->getCookieParams()[self::CookieName] ?? '';
		if (!is_string($token) || !str_contains($token, '.')) {
			return false;
		}

		[$expires, $signature] = explode('.', $token, 2);

		return ctype_digit($expires)
			&& (int) $expires > time()
			&& hash_equals($this->sign($expires), $signature);
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

	public function isHttps(ServerRequestInterface $request): bool
	{
		if ($request->getUri()->getScheme() === 'https') {
			return true;
		}

		// Only a trusted proxy may say that the visitor used HTTPS; anybody else could send the header.
		return strtolower($this->proxies->forwarded($request, 'X-Forwarded-Proto') ?? '') === 'https';
	}

	private function sign(string $expires): string
	{
		// The password hash is part of the key: changing the password logs everybody out.
		return hash_hmac('sha256', $expires, $this->config->secret . '|' . $this->config->passwordHash);
	}

	private function cookie(string $name, string $value, int $expires, ServerRequestInterface $request): string
	{
		$parts = [
			$name . '=' . rawurlencode($value),
			'Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT',
			'Path=/',
			'HttpOnly',
			'SameSite=Lax',
		];
		if ($this->isHttps($request)) {
			$parts[] = 'Secure';
		}

		return implode('; ', $parts);
	}
}
