<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Http\Exception\NotFoundException;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Data\Model\Record;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\View;
use const JSON_UNESCAPED_UNICODE;

/**
 * Map, parish records, documents, notes and search.
 */
final readonly class SourcesController
{
	/** Markdown notes exported by the skill: URL => [file, title]. */
	private const Notes = [
		'zajimavosti' => ['zajimavosti', 'Zajímavosti'],
		'pribeh' => ['pribeh', 'Příběh rodu'],
		'denik' => ['denik', 'Deník hledání'],
	];

	public function __construct(private Repository $repository, private View $view)
	{
	}

	#[RouteGet('/mapa')]
	public function map(): ResponseInterface
	{
		$places = [];
		foreach ($this->repository->places() as $place) {
			$groups = [];
			foreach ($place->lines as $group) {
				$k = $group->k !== 0 ? $group->k : null;
				$groups[] = [
					'color' => $this->repository->lineColor($k),
					'line' => $this->repository->lineByNumber($k)->name ?? 'Nejbližší rodina',
					'people' => array_values(array_filter(array_map(function (string $slug): ?array {
						$person = $this->repository->person($slug);

						return $person === null
							? null
							: [
								'name' => $person->name,
								'y' => $person->y,
								'url' => $this->view->personUrl(
									$slug,
								)];
					}, $group->people))),
				];
			}
			$places[] = [
				'name' => $place->name,
				'lat' => $place->lat,
				'lon' => $place->lon,
				'years' => $place->years,
				'hypothesis' => $place->hypothesis,
				'groups' => $groups,
			];
		}

		return $this->view->page('map', [
			'title' => 'Mapa',
			'nav' => 'map',
			'places' => $places,
			'lines' => $this->repository->lines(),
		]);
	}

	#[RouteGet('/zapisy')]
	public function records(): ResponseInterface
	{
		$records = $this->repository->records();
		usort($records, static fn (Record $a, Record $b): int => $a->year() <=> $b->year());

		return $this->view->page('records', [
			'title' => 'Zápisy z matrik',
			'nav' => 'records',
			'records' => $records,
		]);
	}

	/** @param array<string, string> $args */
	#[RouteGet('/zapis/{key}')]
	public function record(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$record = $this->repository->record($args['key']);
		if ($record === null) {
			throw new NotFoundException();
		}

		return $this->view->page('record', [
			'title' => $record->title,
			'nav' => 'records',
			'record' => $record,
		]);
	}

	#[RouteGet('/dokumenty')]
	public function documents(): ResponseInterface
	{
		return $this->view->page('documents', [
			'title' => 'Fotografie a dokumenty',
			'nav' => 'documents',
			'documents' => $this->repository->documents(),
		]);
	}

	#[RouteGet('/hledame')]
	public function openQuestions(): ResponseInterface
	{
		return $this->view->page('questions', [
			'title' => 'Co ještě hledáme',
			'nav' => 'notes',
			'issues' => $this->repository->issues(),
			'hypotheses' => $this->repository->hypotheses(),
			'archive' => $this->repository->markdown('dotazy'),
		]);
	}

	/** @param array<string, string> $args */
	#[RouteGet('/{note:zajimavosti|pribeh|denik}')]
	public function note(ServerRequestInterface $request, array $args): ResponseInterface
	{
		[$file, $title] = self::Notes[$args['note']];
		$text = $this->repository->markdown($file);
		if ($text === null) {
			throw new NotFoundException();
		}

		return $this->view->page('note', [
			'title' => $title,
			'nav' => 'notes',
			'html' => $this->view->markdown($text),
			'wide' => $args['note'] === 'denik',
		]);
	}

	#[RouteGet('/hledat')]
	public function search(ServerRequestInterface $request): ResponseInterface
	{
		return $this->view->page('search', [
			'title' => 'Hledat',
			'nav' => 'search',
			'query' => self::queryString($request, 'q'),
		]);
	}

	/** Compact index for client-side search. */
	#[RouteGet('/data/hledani.json')]
	public function searchIndex(): ResponseInterface
	{
		$items = [];
		foreach ($this->repository->people() as $person) {
			$items[] = [
				't' => 'osoba',
				'n' => $person->name,
				'd' => trim($person->y . ' · ' . $person->rel, ' ·'),
				'x' => implode(' ', $person->facts) . ' ' . implode(' ', $this->repository->placeNamesOf($person->slug)),
				'u' => $this->view->personUrl($person->slug),
				'c' => $this->repository->lineColor($person->line),
			];
		}
		foreach ($this->repository->records() as $record) {
			$items[] = [
				't' => 'zápis',
				'n' => $record->title,
				'd' => $record->date,
				'x' => $record->summary . ' ' . $record->vdesc,
				'u' => '/zapis/' . rawurlencode($record->key),
				'c' => '#9a8f7d',
			];
		}
		foreach ($this->repository->places() as $place) {
			$items[] = [
				't' => 'místo',
				'n' => $place->name,
				'd' => '',
				'x' => '',
				'u' => '/mapa#' . rawurlencode($place->name),
				'c' => '#6b8f71'];
		}

		return new JsonResponse($items, 200, ['Cache-Control' => 'private, max-age=300'], JSON_UNESCAPED_UNICODE);
	}

	private static function queryString(ServerRequestInterface $request, string $name): string
	{
		$value = $request->getQueryParams()[$name] ?? null;

		return is_string($value) ? $value : '';
	}
}
