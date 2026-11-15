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
use Rodokmen\Site\Image\Thumbnails;

/**
 * Serves record crops and family photos only to logged-in visitors (they live outside the web root), and their
 * smaller WebP copies for `srcset`.
 */
final readonly class ImageController
{
	private const Folders = ['nahled' => 'img', 'plny' => 'full'];

	public function __construct(private Repository $repository, private Thumbnails $thumbnails)
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

		return $this->file($request, $path, $file, 'image/jpeg');
	}

	/**
	 * WebP copy of img/{name}.jpg at most {width} wide; the JPEG itself when no copy can be made (GD without WebP,
	 * a source too large to decode), so that a page never shows a broken image.
	 *
	 * @param array<string, string> $args
	 */
	#[RouteGet('/obrazek/nahled/{width:480|960|1440}/{file}')]
	public function thumbnail(ServerRequestInterface $request, array $args): ResponseInterface
	{
		if (preg_match('/^([a-z0-9_\-]+)\.webp$/', $args['file'], $m) !== 1) {
			throw new NotFoundException();
		}

		$thumbnail = $this->thumbnails->thumbnail($m[1], (int) $args['width']);
		if ($thumbnail !== null) {
			return $this->file($request, $thumbnail, basename($thumbnail), 'image/webp');
		}

		$source = $this->repository->dataDir() . '/img/' . $m[1] . '.jpg';
		if (!is_file($source)) {
			throw new NotFoundException();
		}

		return $this->file($request, $source, $m[1] . '.jpg', 'image/jpeg');
	}

	private function file(ServerRequestInterface $request, string $path, string $name, string $type): ResponseInterface
	{
		$etag = '"' . md5($name . filemtime($path) . filesize($path)) . '"';
		if ($request->getHeaderLine('If-None-Match') === $etag) {
			return (new Response('php://memory', 304))->withHeader('ETag', $etag);
		}

		return (new Response(new Stream($path, 'r')))
			->withHeader('Content-Type', $type)
			->withHeader('Content-Length', (string) filesize($path))
			->withHeader('Cache-Control', 'private, max-age=86400')
			->withHeader('ETag', $etag);
	}
}
