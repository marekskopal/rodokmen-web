<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Http;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Http\AuthMiddleware;
use Rodokmen\Site\Tests\Support\Fixture;

final class AuthMiddlewareTest extends TestCase
{
	private Auth $auth;

	private AuthMiddleware $middleware;

	private RequestHandlerInterface $handler;

	protected function setUp(): void
	{
		$this->auth = new Auth(Fixture::config(sys_get_temp_dir()));
		$this->middleware = new AuthMiddleware($this->auth);
		$this->handler = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new TextResponse('page');
			}
		};
	}

	public function testAnonymousVisitorIsSentToLogin(): void
	{
		$response = $this->middleware->process(new ServerRequest([], [], '/osoba/jan?x=1'), $this->handler);

		self::assertSame(302, $response->getStatusCode());
		self::assertSame('/prihlaseni?k=' . rawurlencode('/osoba/jan?x=1'), $response->getHeaderLine('Location'));
	}

	public function testLoginPageIsPublic(): void
	{
		$response = $this->middleware->process(new ServerRequest([], [], '/prihlaseni'), $this->handler);

		self::assertSame('page', (string) $response->getBody());
	}

	public function testLoggedInVisitorPasses(): void
	{
		self::assertSame(1, preg_match('/^rk_auth=([^;]+);/', $this->auth->loginCookie(new ServerRequest()), $m));
		$request = new ServerRequest([], [], '/osoba/jan')->withCookieParams([Auth::CookieName => rawurldecode($m[1])]);

		self::assertSame('page', (string) $this->middleware->process($request, $this->handler)->getBody());
	}
}
