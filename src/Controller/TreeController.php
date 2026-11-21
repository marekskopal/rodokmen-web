<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response\RedirectResponse;
use League\Route\Http\Exception\NotFoundException;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Model\Story;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\Stories;
use Rodokmen\Site\View\AncestorTable;
use Rodokmen\Site\View\FanChart;
use Rodokmen\Site\View\LineSummaries;
use Rodokmen\Site\View\View;

/**
 * Home page, ancestor explorer, person detail and the generations overview.
 */
final readonly class TreeController
{
	private const ExplorerDepth = 3;

	public function __construct(
		private Repository $repository,
		private HomePerson $homePerson,
		private Auth $auth,
		private View $view,
		private FanChart $fanChart,
		private AncestorTable $ancestorTable,
		private LineSummaries $lineSummaries,
		private Stories $stories,
	)
	{
	}

	#[RouteGet('/')]
	public function home(ServerRequestInterface $request): ResponseInterface
	{
		return $this->view->page('home', [
			'title' => $this->repository->title(),
			'nav' => 'home',
			'home' => $this->repository->person($this->homePerson->slug($request)),
			'cards' => $this->lineSummaries->all(),
			'stats' => $this->repository->stats(),
			'stories' => $this->storyTitles(),
			'story' => $this->stories->main(),
			'otherStories' => array_values(array_filter($this->stories->all(), static fn (Story $story): bool => !$story->main)),
			'intro' => $this->repository->siteConfig()->intro,
			'mapPlaces' => $this->mapPlaces(),
			'needsMap' => $this->repository->places() !== [],
		]);
	}

	/** @param array<string, string> $args */
	#[RouteGet('/predci[/{slug}]')]
	public function explorer(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$homeSlug = $this->homePerson->slug($request);
		$person = $this->repository->person($args['slug'] ?? $homeSlug);
		if ($person === null) {
			throw new NotFoundException();
		}

		$generations = $this->repository->ancestorGenerations($person->slug, self::ExplorerDepth);
		$deeper = [];
		foreach ($generations[array_key_last($generations)] as $slug) {
			$deeper[] = $slug !== null && count($generations) > self::ExplorerDepth
				? $this->repository->ancestorCount($slug)
				: 0;
		}

		return $this->view->page('explorer', [
			'title' => 'Předci – ' . $person->name,
			'nav' => 'explorer',
			'person' => $person,
			'generations' => $generations,
			'deeper' => $deeper,
			'path' => $this->repository->pathBetween($person->slug, $homeSlug),
			'home' => $this->repository->person($homeSlug),
		]);
	}

	/**
	 * Numbered list of a person's ancestors for printing (or saving as PDF); ?generace=N limits the generations,
	 * ?zapisy=1 adds the transcriptions of the register entries.
	 *
	 * @param array<string, string> $args
	 */
	#[RouteGet('/predci/{slug}/tisk')]
	public function printAncestors(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$person = $this->repository->person($args['slug']);
		if ($person === null) {
			throw new NotFoundException();
		}

		$available = $this->ancestorTable->availableDepth($person);
		$query = $request->getQueryParams();
		$requested = $query['generace'] ?? null;
		// At least one generation when there is any (the choice offers 1 … available)
		$depth = is_string($requested) && ctype_digit($requested)
			? max(min(1, $available), min($available, (int) $requested))
			: $available;

		return $this->view->page('print-ancestors', [
			'title' => 'Vývod předků – ' . $person->name,
			'nav' => 'explorer',
			'person' => $person,
			'generations' => $this->ancestorTable->build($person, $depth),
			'depth' => $depth,
			'available' => $available,
			'transcripts' => ($query['zapisy'] ?? null) === '1',
		]);
	}

	/**
	 * Complete ancestor tree of a person, drawn left to right.
	 *
	 * @param array<string, string> $args
	 */
	#[RouteGet('/strom[/{slug}]')]
	public function fullTree(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$homeSlug = $this->homePerson->slug($request);
		$person = $this->repository->person($args['slug'] ?? $homeSlug);
		if ($person === null) {
			throw new NotFoundException();
		}

		return $this->view->page('tree', [
			'title' => 'Celý strom – ' . $person->name,
			'nav' => 'explorer',
			'wide' => true,
			'person' => $person,
			'count' => $this->repository->ancestorCount($person->slug),
			'home' => $this->repository->person($homeSlug),
		]);
	}

	/**
	 * Ancestors of a person as a 360° fan chart; ?generace=N limits the number of rings.
	 *
	 * @param array<string, string> $args
	 */
	#[RouteGet('/vejir[/{slug}]')]
	public function fan(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$homeSlug = $this->homePerson->slug($request);
		$person = $this->repository->person($args['slug'] ?? $homeSlug);
		if ($person === null) {
			throw new NotFoundException();
		}

		$available = $this->fanChart->availableDepth($person);
		$requested = $request->getQueryParams()['generace'] ?? null;
		$depth = is_string($requested) && ctype_digit($requested) ? (int) $requested : FanChart::DefaultDepth;
		$depth = max(1, min($available, $depth));

		return $this->view->page('fan', [
			'title' => 'Vějíř předků – ' . $person->name,
			'nav' => 'explorer',
			'wide' => true,
			'person' => $person,
			'depth' => $depth,
			'available' => $available,
			'svg' => $this->fanChart->render($person, $depth),
			'count' => $this->repository->ancestorCount($person->slug),
			'home' => $this->repository->person($homeSlug),
		]);
	}

	/** @param array<string, string> $args */
	#[RouteGet('/osoba/{slug}')]
	public function person(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$person = $this->repository->person($args['slug']);
		if ($person === null) {
			throw new NotFoundException();
		}
		if ($person->slug !== $args['slug']) {
			return new RedirectResponse($this->view->personUrl($person->slug), 301);
		}

		$homeSlug = $this->homePerson->slug($request);
		$ownFamily = $this->repository->family($person->ownFamily);

		return $this->view->page('person', [
			'title' => $person->name,
			'nav' => 'explorer',
			'person' => $person,
			'records' => $this->repository->recordsByKeys($person->records),
			'places' => $this->repository->placesOf($person->slug),
			'needsMap' => $this->repository->placesOf($person->slug) !== [],
			'path' => $this->repository->pathBetween($person->slug, $homeSlug),
			'home' => $this->repository->person($homeSlug),
			'isHome' => $person->slug === $homeSlug,
			'siblings' => $person->siblings(),
			'relatives' => $person->otherRelatives(),
			// Half-siblings come from another marriage, so they are not children of this couple.
			'childSiblings' => $ownFamily?->otherChildren() ?? [],
			'line' => $this->repository->lineByNumber($person->line),
			'ownFamily' => $ownFamily,
			'documents' => $person->docs,
		]);
	}

	/** @param array<string, string> $args */
	#[RouteGet('/rod/{slug}')]
	public function line(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$line = $this->repository->line($args['slug']);
		if ($line === null) {
			throw new NotFoundException();
		}

		return $this->view->page('line', [
			'title' => 'Rod ' . $line->name,
			'nav' => 'home',
			'summary' => $this->lineSummaries->of($line),
			'byGeneration' => $this->lineSummaries->generations($line),
			'story' => $this->stories->forLine($line->slug),
		]);
	}

	#[RouteGet('/generace')]
	public function generations(ServerRequestInterface $request): ResponseInterface
	{
		$byGeneration = [];
		foreach ($this->repository->people() as $person) {
			$byGeneration[$person->gen][] = $person;
		}
		ksort($byGeneration);
		foreach ($byGeneration as &$people) {
			usort($people, static fn (Person $a, Person $b): int => $a->k <=> $b->k);
		}
		unset($people);

		$rod = $request->getQueryParams()['rod'] ?? null;
		$line = is_string($rod) ? $rod : '';

		return $this->view->page('generations', [
			'title' => 'Generace',
			'nav' => 'generations',
			'byGeneration' => $byGeneration,
			'lines' => $this->repository->lines(),
			'activeLine' => $this->repository->line($line) !== null ? $line : '',
		]);
	}

	/** @param array<string, string> $args */
	#[RoutePost('/vychozi/{slug}')]
	public function setHome(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$person = $this->repository->person($args['slug']);
		if ($person === null) {
			throw new NotFoundException();
		}

		$reset = $person->slug === $this->homePerson->defaultSlug();
		$cookie = HomePerson::CookieName . '=' . ($reset ? '' : rawurlencode($person->slug))
			. '; Path=/; SameSite=Lax; HttpOnly'
			. '; Expires=' . gmdate('D, d M Y H:i:s', $reset ? 1 : time() + 365 * 86400) . ' GMT'
			. ($this->auth->isHttps($request) ? '; Secure' : '');

		return (new RedirectResponse($this->view->explorerUrl($person->slug)))->withAddedHeader('Set-Cookie', $cookie);
	}

	/**
	 * Places for the map on the home page, each with the colours of its lines.
	 *
	 * @return list<array{name: string, lat: float, lon: float, colors: list<string>}>
	 */
	private function mapPlaces(): array
	{
		$places = [];
		foreach ($this->repository->places() as $place) {
			$colors = [];
			foreach ($place->lines as $group) {
				$colors[] = $this->repository->lineColor($group->k !== 0 ? $group->k : null);
			}
			$places[] = [
				'name' => $place->name,
				'lat' => $place->lat,
				'lon' => $place->lon,
				// A place without any line still gets a visible marker.
				'colors' => $colors !== [] ? array_values(array_unique($colors)) : [$this->repository->lineColor(null)],
			];
		}

		return $places;
	}

	/** @return list<string> */
	private function storyTitles(): array
	{
		$text = $this->repository->markdown('zajimavosti') ?? '';
		preg_match_all('/^##\s+(.+)$/m', $text, $matches);

		return array_slice($matches[1], 0, 4);
	}
}
