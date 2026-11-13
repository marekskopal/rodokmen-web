<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use League\Route\Http\Exception\NotFoundException;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Data\Repository;

/**
 * Serves record crops and family photos only to logged-in visitors (they live outside the web root).
 */
final readonly class ImageController
{
	private const Folders = ['nahled' => 'img', 'plny' => 'full'];

	public function __construct(private Repository $repository)
	{
	}

	/** @param array<string, string> $args */
	#[RouteGet('/obrazek/{size:nahled|plny}/{file}')]
	public function show(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$file = $args['file'];
		if (preg_match('/^[a-z0-9_\-]+\.jpg$/', $file) !== 1) {
			throw new NotFoundException();
		}

		$path = $this->repository->dataDir() . '/' . self::Folders[$args['size']] . '/' . $file;
		if (!is_file($path) && $args['size'] === 'plny') {
			$path = $this->repository->dataDir() . '/img/' . $file;
		}
		if (!is_file($path)) {
			throw new NotFoundException();
		}

		$etag = '"' . md5($file . filemtime($path) . filesize($path)) . '"';
		if ($request->getHeaderLine('If-None-Match') === $etag) {
			return (new Response('php://memory', 304))->withHeader('ETag', $etag);
		}

		return (new Response(new Stream($path, 'r')))
			->withHeader('Content-Type', 'image/jpeg')
			->withHeader('Content-Length', (string) filesize($path))
			->withHeader('Cache-Control', 'private, max-age=86400')
			->withHeader('ETag', $etag);
	}
}
