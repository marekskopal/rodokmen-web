<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Auth;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Config;
use Rodokmen\Site\Tests\Support\Fixture;
use const PASSWORD_BCRYPT;

final class AuthTest extends TestCase
{
	public function testConfigured(): void
	{
		self::assertTrue($this->auth()->isConfigured());
		self::assertFalse($this->auth(passwordHash: '')->isConfigured());
		self::assertFalse($this->auth(secret: 'short')->isConfigured());
	}

	public function testVerifyPassword(): void
	{
		self::assertTrue($this->auth()->verifyPassword('tajne-heslo'));
		self::assertFalse($this->auth()->verifyPassword('spatne'));
		self::assertFalse($this->auth(secret: 'short')->verifyPassword('tajne-heslo'));
	}

	public function testLoginCookieRoundTrip(): void
	{
		$auth = $this->auth();
		$token = $this->token($auth->loginCookie(new ServerRequest()));

		self::assertTrue($auth->isLoggedIn($this->withToken($token)));
		self::assertFalse($auth->isLoggedIn(new ServerRequest()));
	}

	public function testTamperedOrExpiredTokenIsRejected(): void
	{
		$auth = $this->auth();
		$token = $this->token($auth->loginCookie(new ServerRequest()));
		$expires = (int) strstr($token, '.', true);
		$signature = substr($token, strlen((string) $expires) + 1);

		self::assertFalse($auth->isLoggedIn($this->withToken(($expires + 100) . '.' . $signature)));
		self::assertFalse($auth->isLoggedIn($this->withToken($expires . '.x' . $signature)));
		self::assertFalse($auth->isLoggedIn($this->withToken('no-dot')));
		self::assertFalse($auth->isLoggedIn($this->withToken('abc.' . $signature)));

		$past = (string) (time() - 10);
		$secret = (string) getenv('SITE_SECRET') . '|' . (string) getenv('SITE_PASSWORD_HASH');
		self::assertFalse($auth->isLoggedIn($this->withToken($past . '.' . hash_hmac('sha256', $past, $secret))));
	}

	public function testChangedPasswordLogsEverybodyOut(): void
	{
		$token = $this->token($this->auth()->loginCookie(new ServerRequest()));
		$other = $this->auth(passwordHash: password_hash('jine', PASSWORD_BCRYPT, ['cost' => 4]));

		self::assertFalse($other->isLoggedIn($this->withToken($token)));
	}

	public function testCookieAttributes(): void
	{
		$auth = $this->auth();
		$plain = $auth->loginCookie(new ServerRequest());
		$secure = $auth->loginCookie(new ServerRequest()->withUri(new Uri('https://rodokmen.example/')));

		self::assertStringContainsString('HttpOnly', $plain);
		self::assertStringContainsString('SameSite=Lax', $plain);
		self::assertStringNotContainsString('Secure', $plain);
		self::assertStringContainsString('; Secure', $secure);
		self::assertStringStartsWith(Auth::CookieName . '=;', $auth->logoutCookie(new ServerRequest()));
		self::assertStringContainsString('Expires=Thu, 01 Jan 1970', $auth->logoutCookie(new ServerRequest()));
	}

	public function testHttpsDetection(): void
	{
		$proxied = new ServerRequest()->withHeader('X-Forwarded-Proto', 'HTTPS');

		self::assertTrue($this->auth()->isHttps(new ServerRequest()->withUri(new Uri('https://x/'))));
		self::assertFalse($this->auth()->isHttps($proxied));
		self::assertTrue($this->auth(trustProxy: true)->isHttps($proxied));
	}

	private function auth(?string $passwordHash = null, ?string $secret = null, bool $trustProxy = false): Auth
	{
		$base = Fixture::config(sys_get_temp_dir());

		return new Auth(new Config(
			$base->rootDir,
			$base->dataDir,
			$base->cacheDir,
			$passwordHash ?? $base->passwordHash,
			$secret ?? $base->secret,
			null,
			$trustProxy,
			30,
			false,
		));
	}

	private function token(string $setCookie): string
	{
		self::assertSame(1, preg_match('/^' . Auth::CookieName . '=([^;]+);/', $setCookie, $m));

		return rawurldecode($m[1]);
	}

	private function withToken(string $token): ServerRequest
	{
		return new ServerRequest()->withCookieParams([Auth::CookieName => $token]);
	}
}
