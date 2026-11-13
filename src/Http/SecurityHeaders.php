<?php

declare(strict_types=1);

namespace Rodokmen\Site\Http;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Config;

/**
 * HTTPS enforcement and security headers. Applied by the Kernel to every response,
 * including error pages and redirects (which bypass route middleware).
 */
final readonly class SecurityHeaders
{
	/** One year; browsers then refuse plain HTTP for this host. */
	private const HstsMaxAge = 31536000;

	private const ContentSecurityPolicy = [
		"default-src 'self'",
		"script-src 'self' https://cdnjs.cloudflare.com",
		"style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
		"img-src 'self' data: https://tile.openstreetmap.org",
		"font-src 'self'",
		"connect-src 'self'",
		"object-src 'none'",
		"base-uri 'self'",
		"form-action 'self'",
		"frame-ancestors 'none'",
		'upgrade-insecure-requests',
	];

	public function __construct(private Config $config, private Auth $auth)
	{
	}

	/** Permanent redirect to HTTPS for plain HTTP requests (except local development). */
	public function redirectToHttps(ServerRequestInterface $request): ?ResponseInterface
	{
		if (!$this->config->forceHttps || $this->auth->isHttps($request) || $this->isLocal($request)) {
			return null;
		}

		$uri = $request->getUri()->withScheme('https')->withPort(null);

		// 308 keeps the method, so a login form posted over HTTP is not silently turned into GET.
		return new RedirectResponse((string) $uri, $request->getMethod() === 'GET' || $request->getMethod() === 'HEAD' ? 301 : 308);
	}

	public function apply(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$response = $response
			->withHeader('Content-Security-Policy', implode('; ', self::ContentSecurityPolicy))
			->withHeader('X-Content-Type-Options', 'nosniff')
			->withHeader('X-Frame-Options', 'DENY')
			->withHeader('Referrer-Policy', 'same-origin')
			->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()')
			->withHeader('Cross-Origin-Opener-Policy', 'same-origin')
			->withHeader('Cross-Origin-Resource-Policy', 'same-origin')
			->withHeader('X-Robots-Tag', 'noindex, nofollow');

		if ($this->auth->isHttps($request)) {
			$response = $response->withHeader('Strict-Transport-Security', 'max-age=' . self::HstsMaxAge . '; includeSubDomains');
		}

		if (!$response->hasHeader('Cache-Control')) {
			$response = $response->withHeader('Cache-Control', 'private, no-store');
		}

		return $response;
	}

	private function isLocal(ServerRequestInterface $request): bool
	{
		return in_array($request->getUri()->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
	}
}
