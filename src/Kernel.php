<?php

declare(strict_types=1);

namespace Rodokmen\Site;

use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Http\Exception\MethodNotAllowedException;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Strategy\ApplicationStrategy;
use MarekSkopal\Router\Builder\RouterBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Http\AuthMiddleware;
use Rodokmen\Site\Http\SecurityHeaders;
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

	public static function create(string $rootDir): self
	{
		$config = Config::fromEnvironment($rootDir);
		$container = new Container();
		$container->set(Config::class, $config);
		$container->set(Container::class, $container);

		return new self($container);
	}

	public function run(): void
	{
		$request = ServerRequestFactory::fromGlobals();
		(new SapiEmitter())->emit($this->handle($request));
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
			error_log((string) $e);

			$repository = $this->container->service(Repository::class);
			$message = $repository->isAvailable()
				? 'Něco se pokazilo. Zkuste to prosím později.'
				: 'Chybí data rodokmenu (site.json). Nahrajte je z projektu rodokmenu příkazem bin/rodokmen-sync.';

			return $this->container->service(View::class)->page('error', ['title' => 'Chyba', 'message' => $message], 500);
		}
	}
}
