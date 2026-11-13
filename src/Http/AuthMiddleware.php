<?php

declare(strict_types=1);

namespace Rodokmen\Site\Http;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rodokmen\Site\Auth\Auth;

/**
 * Every page except the login form requires a valid login cookie. The sync API (/api/) has its own token.
 */
final readonly class AuthMiddleware implements MiddlewareInterface
{
	private const PublicPaths = ['/prihlaseni'];

	private const ApiPrefix = '/api/';

	public function __construct(private Auth $auth)
	{
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$path = $request->getUri()->getPath();
		if (in_array($path, self::PublicPaths, true) || str_starts_with($path, self::ApiPrefix) || $this->auth->isLoggedIn($request)) {
			return $handler->handle($request);
		}

		$target = $path . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : '');

		return new RedirectResponse('/prihlaseni?k=' . rawurlencode($target));
	}
}
