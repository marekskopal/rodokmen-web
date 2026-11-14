<?php

declare(strict_types=1);

namespace Rodokmen\Site;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\ServerRequestFilter\DoNotFilter;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Http\Exception\MethodNotAllowedException;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Strategy\ApplicationStrategy;
use MarekSkopal\Router\Builder\RouterBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Http\AuthMiddleware;
use Rodokmen\Site\Http\SecurityHeaders;
use Rodokmen\Site\Logger\Logger;
use Rodokmen\Site\View\View;
use Throwable;

/**
 * Boots the application: configuration, container, attribute routes and middleware.
 */
final readonly class Kernel
{
	private function __construct(private Container $container)
	{
	}

	/** @param bool $handleErrors let Tracy handle PHP errors (public/index.php; not in tests) */
	public static function create(string $rootDir, bool $handleErrors = false): self
	{
		$config = Config::fromEnvironment($rootDir);
		$container = new Container();
		$container->set(Config::class, $config);
		$container->set(Container::class, $container);
		$container->set(LoggerInterface::class, Logger::initLogger($config->logDir, $config->logDebug, $config->logStderr, $handleErrors));

		return new self($container);
	}

	public function run(): void
	{
		(new SapiEmitter())->emit($this->handle(self::requestFromGlobals()));
	}

	/**
	 * The request from PHP's globals, exactly as the client (or the proxy) sent it. Diactoros would otherwise
	 * believe X-Forwarded-Proto/Host/Port from any private address and rewrite the URI; forwarded headers are
	 * trusted only from SITE_TRUSTED_PROXIES (Http\TrustedProxies).
	 *
	 * @param array<string, mixed>|null $server $_SERVER by default
	 */
	public static function requestFromGlobals(?array $server = null): ServerRequestInterface
	{
		return ServerRequestFactory::fromGlobals($server, null, null, null, null, new DoNotFilter());
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$security = $this->container->service(SecurityHeaders::class);

		$response = $security->redirectToHttps($request) ?? $this->dispatch($request);

		return $security->apply($request, $response);
	}

	private function dispatch(ServerRequestInterface $request): ResponseInterface
	{
		$config = $this->container->service(Config::class);

		$router = (new RouterBuilder())
			->setClassDirectories([$config->rootDir . '/src/Controller'])
			->build();

		$strategy = new ApplicationStrategy();
		$strategy->setContainer($this->container);
		$router->setStrategy($strategy);
		$router->middleware($this->container->service(AuthMiddleware::class));

		try {
			return $router->dispatch($request);
		} catch (NotFoundException | MethodNotAllowedException) {
			// Unknown URLs do not reveal anything to visitors who are not logged in.
			if (!$this->container->service(Auth::class)->isLoggedIn($request)) {
				return new RedirectResponse('/prihlaseni');
			}

			return $this->container->service(View::class)->page('error', [
				'title' => 'Stránka nenalezena',
				'message' => 'Tahle stránka neexistuje. Možná se změnila adresa osoby nebo zápisu.',
			], 404);
		} catch (Throwable $e) {
			$repository = $this->container->service(Repository::class);
			$available = $repository->isAvailable();
			$this->logger()->error(
				$e->getMessage(),
				['exception' => $e, 'method' => $request->getMethod(), 'path' => $request->getUri()->getPath()],
			);

			return $this->errorPage($available
				? 'Něco se pokazilo. Zkuste to prosím později.'
				: 'Chybí data rodokmenu (site.json). Nahrajte je z projektu rodokmenu příkazem bin/rodokmen-sync.', $available ? 500 : 503);
		}
	}

	/** Error page; when even the layout cannot be rendered (broken data), a plain page without it. */
	private function errorPage(string $message, int $status): ResponseInterface
	{
		try {
			return $this->container->service(View::class)->page('error', ['title' => 'Chyba', 'message' => $message], $status);
		} catch (Throwable $e) {
			$this->logger()->critical('Cannot render the error page: ' . $e->getMessage(), ['exception' => $e]);

			return new HtmlResponse(
				'<!doctype html><html lang="cs"><meta charset="utf-8"><title>Chyba</title><p>' . htmlspecialchars($message) . '</p></html>',
				$status,
			);
		}
	}

	private function logger(): LoggerInterface
	{
		return $this->container->service(LoggerInterface::class);
	}
}
