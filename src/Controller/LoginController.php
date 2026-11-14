<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response\RedirectResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Auth\LoginThrottle;
use Rodokmen\Site\Auth\ThrottleScope;
use Rodokmen\Site\View\View;

final readonly class LoginController
{
	public function __construct(private Auth $auth, private LoginThrottle $throttle, private View $view, private LoggerInterface $logger)
	{
	}

	#[RouteGet('/prihlaseni')]
	public function form(ServerRequestInterface $request): ResponseInterface
	{
		if ($this->auth->isLoggedIn($request)) {
			return new RedirectResponse($this->target($request->getQueryParams()['k'] ?? null));
		}

		return $this->showForm($request);
	}

	#[RoutePost('/prihlaseni')]
	public function login(ServerRequestInterface $request): ResponseInterface
	{
		$target = $this->target(self::bodyField($request, 'k'));

		$wait = $this->throttle->retryAfter($request, ThrottleScope::Login);
		if ($wait > 0) {
			$this->logger->warning('Login throttled', ['ip' => $this->throttle->clientIp($request)]);

			return $this->showForm($request, 'Příliš mnoho pokusů. Zkuste to znovu za ' . (int) ceil($wait / 60) . ' min.', 429)
				->withHeader('Retry-After', (string) $wait);
		}

		if (!$this->auth->verifyPassword(self::bodyField($request, 'heslo') ?? '')) {
			$this->throttle->registerFailure($request, ThrottleScope::Login);
			$this->logger->warning('Wrong password', ['ip' => $this->throttle->clientIp($request)]);
			usleep(400000);

			return $this->showForm($request, 'Heslo nesouhlasí.', 401);
		}

		$this->throttle->reset($request, ThrottleScope::Login);

		return (new RedirectResponse($target))->withAddedHeader('Set-Cookie', $this->auth->loginCookie($request));
	}

	#[RoutePost('/odhlasit')]
	public function logout(ServerRequestInterface $request): ResponseInterface
	{
		return (new RedirectResponse('/prihlaseni'))->withAddedHeader('Set-Cookie', $this->auth->logoutCookie($request));
	}

	private function showForm(ServerRequestInterface $request, ?string $error = null, int $status = 200): ResponseInterface
	{
		$query = $request->getQueryParams()['k'] ?? null;
		$target = $this->target(is_string($query) ? $query : self::bodyField($request, 'k'));

		return $this->view->page('login', [
			'title' => 'Přihlášení',
			'bare' => true,
			'error' => $error,
			'configured' => $this->auth->isConfigured(),
			'target' => $target,
		], $status);
	}

	/** Only local paths are allowed as a redirect target. */
	private function target(mixed $value): string
	{
		$value = is_string($value) ? $value : '/';

		return str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '\\') ? $value : '/';
	}

	private static function bodyField(ServerRequestInterface $request, string $name): ?string
	{
		$body = $request->getParsedBody();
		$value = is_array($body) ? ($body[$name] ?? null) : null;

		return is_string($value) ? $value : null;
	}
}
