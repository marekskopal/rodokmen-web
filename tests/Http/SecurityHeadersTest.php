<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Http;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Http\SecurityHeaders;
use Rodokmen\Site\Http\TrustedProxies;
use Rodokmen\Site\Tests\Support\Fixture;

final class SecurityHeadersTest extends TestCase
{
	public function testRedirectsPlainHttpWhenForced(): void
	{
		$headers = $this->headers(true);

		$get = $headers->redirectToHttps(new ServerRequest([], [], 'http://rodokmen.example:80/osoba/x?y=1', 'GET'));
		self::assertNotNull($get);
		self::assertSame(301, $get->getStatusCode());
		self::assertSame('https://rodokmen.example/osoba/x?y=1', $get->getHeaderLine('Location'));

		$post = $headers->redirectToHttps(new ServerRequest([], [], 'http://rodokmen.example/prihlaseni', 'POST'));
		self::assertSame(308, $post?->getStatusCode());
	}

	public function testNoRedirectLocallyOrOverHttpsOrWhenDisabled(): void
	{
		self::assertNull($this->headers(true)->redirectToHttps(new ServerRequest([], [], 'http://localhost:8080/')));
		self::assertNull($this->headers(true)->redirectToHttps(new ServerRequest([], [], 'https://rodokmen.example/')));
		self::assertNull($this->headers(false)->redirectToHttps(new ServerRequest([], [], 'http://rodokmen.example/')));
	}

	public function testForwardedHostAndProtoOnlyFromTrustedProxies(): void
	{
		// nginx forwards to the upstream address by default and puts the visitor's host into X-Forwarded-Host.
		$proxied = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://127.0.0.1:8080/osoba/x', 'GET')
			->withHeader('X-Forwarded-Host', 'rodokmen.example');
		$redirect = $this->headers(true, ['127.0.0.1'])->redirectToHttps($proxied);
		self::assertSame('https://rodokmen.example/osoba/x', $redirect?->getHeaderLine('Location'));

		// The proxy says the visitor used HTTPS (chained proxies append their own value).
		self::assertNull($this->headers(true, ['127.0.0.1'])->redirectToHttps($proxied->withHeader('X-Forwarded-Proto', 'https, http')));

		// A client that is not a trusted proxy can set neither.
		$direct = new ServerRequest(['REMOTE_ADDR' => '203.0.113.5'], [], 'http://rodokmen.example/', 'GET')
			->withHeader('X-Forwarded-Host', 'localhost')
			->withHeader('X-Forwarded-Proto', 'https');
		self::assertSame(
			'https://rodokmen.example/',
			$this->headers(true, ['127.0.0.1'])->redirectToHttps($direct)?->getHeaderLine('Location'),
		);

		// A malformed forwarded host is ignored: the request's own host is used.
		$odd = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://rodokmen.internal/osoba/x', 'GET')
			->withHeader('X-Forwarded-Host', 'evil.example/path');
		self::assertSame(
			'https://rodokmen.internal/osoba/x',
			$this->headers(true, ['127.0.0.1'])->redirectToHttps($odd)?->getHeaderLine('Location'),
		);
	}

	public function testAppliesSecurityHeaders(): void
	{
		$response = $this->headers(false)->apply(new ServerRequest(), new HtmlResponse('x'));

		self::assertStringContainsString("frame-ancestors 'none'", $response->getHeaderLine('Content-Security-Policy'));
		self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
		self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
		self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
		self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
		self::assertFalse($response->hasHeader('Strict-Transport-Security'));
	}

	public function testHstsOverHttpsAndKeepsCacheControl(): void
	{
		$request = new ServerRequest()->withUri(new Uri('https://rodokmen.example/'));
		$response = $this->headers(false)->apply($request, new HtmlResponse('x')->withHeader('Cache-Control', 'private, max-age=300'));

		self::assertStringStartsWith('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
		self::assertSame('private, max-age=300', $response->getHeaderLine('Cache-Control'));
	}

	/** @param list<string> $trustedProxies */
	private function headers(bool $forceHttps, array $trustedProxies = []): SecurityHeaders
	{
		$config = Fixture::config(sys_get_temp_dir(), trustedProxies: $trustedProxies, forceHttps: $forceHttps);

		return new SecurityHeaders($config, Fixture::auth($config), new TrustedProxies($config));
	}
}
