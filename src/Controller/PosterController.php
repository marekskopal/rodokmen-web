<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use League\Route\Http\Exception\NotFoundException;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\Poster\Poster;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\View;

/**
 * Large-format poster of a person's ancestors (A4 … A0) for printing or saving as PDF in the browser.
 */
final readonly class PosterController
{
	public function __construct(private Repository $repository, private View $view, private Poster $poster)
	{
	}

	/**
	 * ?rozvrzeni=vejir|pulvejir, ?format=a4…a0, ?orientace=na-sirku|na-vysku, ?generace=N, ?styl=…, ?nadpis=…
	 *
	 * @param array<string, string> $args
	 */
	#[RouteGet('/predci/{slug}/plakat')]
	public function poster(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$person = $this->repository->person($args['slug']);
		if ($person === null) {
			throw new NotFoundException();
		}

		$available = $this->poster->availableDepth($person);
		$query = $request->getQueryParams();
		$options = PosterOptions::fromQuery($query, $available);
		// Without a chosen number of generations, the most that stay readable on the chosen paper.
		$readable = $this->poster->readable($person, $options->withDepth($available));
		$auto = !PosterOptions::hasDepth($query);
		if ($auto) {
			$options = $options->withDepth($readable->depth);
		}

		return $this->view->page('poster', [
			'title' => 'Plakát předků – ' . $person->name,
			'nav' => 'explorer',
			'wide' => true,
			'person' => $person,
			'options' => $options,
			'available' => $available,
			'readable' => $readable->depth,
			'auto' => $auto,
			'sheet' => $auto ? $readable : $this->poster->render($person, $options),
		]);
	}
}
