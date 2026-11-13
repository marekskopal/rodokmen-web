<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Auth\LoginThrottle;
use Rodokmen\Site\Config;
use Rodokmen\Site\Sync\DataStore;
use Rodokmen\Site\Sync\InvalidManifestException;
use Rodokmen\Site\Sync\Manifest;
use Rodokmen\Site\Sync\SyncException;
use function count;
use function hash_equals;
use function preg_match;

/**
 * Data upload API used by bin/rodokmen-sync (enabled by SITE_SYNC_TOKEN):
 *  1. POST /api/sync/plan manifest.json → hashes of the files the server is missing
 *     (and whether the website already shows this bundle),
 *  2. PUT /api/sync/file/{sha256} raw file content, once per missing hash,
 *  3. POST /api/sync/commit manifest.json → the new data is published atomically.
 */
final readonly class SyncController
{
	public function __construct(private Config $config, private DataStore $store, private LoginThrottle $throttle)
	{
	}

	#[RoutePost('/api/sync/plan')]
	public function plan(ServerRequestInterface $request): ResponseInterface
	{
		return $this->guarded($request, function () use ($request): JsonResponse {
			$manifest = Manifest::fromJson((string) $request->getBody());
			$missing = $this->store->missing($manifest);

			return new JsonResponse(
				[
					'files' => count($manifest->files),
					'missing' => $missing,
					'current' => $missing === [] && $this->store->isCurrent(
						$manifest,
					)],
			);
		});
	}

	/** @param array<string, string> $args */
	#[RoutePut('/api/sync/file/{sha256:[0-9a-f]{64}}')]
	public function upload(ServerRequestInterface $request, array $args): ResponseInterface
	{
		return $this->guarded($request, function () use ($request, $args): JsonResponse {
			$this->store->upload($args['sha256'], $request->getBody());

			return new JsonResponse(['stored' => $args['sha256']], 201);
		});
	}

	#[RoutePost('/api/sync/commit')]
	public function commit(ServerRequestInterface $request): ResponseInterface
	{
		return $this->guarded($request, function () use ($request): JsonResponse {
			$manifest = Manifest::fromJson((string) $request->getBody());
			$release = $this->store->commit($manifest);

			return new JsonResponse(['release' => $release, 'files' => count($manifest->files)]);
		});
	}

	/** @param callable(): JsonResponse $action */
	private function guarded(ServerRequestInterface $request, callable $action): ResponseInterface
	{
		if ($this->config->syncToken === '') {
			return new JsonResponse(['error' => 'The sync API is disabled (set SITE_SYNC_TOKEN)'], 404);
		}

		$wait = $this->throttle->retryAfter($request);
		if ($wait > 0) {
			return (new JsonResponse(['error' => 'Too many failed attempts'], 429))->withHeader('Retry-After', (string) $wait);
		}

		$header = $request->getHeaderLine('Authorization');
		if (preg_match('/^Bearer\s+(\S+)$/', $header, $m) !== 1 || !hash_equals($this->config->syncToken, $m[1])) {
			$this->throttle->registerFailure($request);

			return new JsonResponse(['error' => 'Invalid token'], 401);
		}

		try {
			return $action();
		} catch (InvalidManifestException $e) {
			return new JsonResponse(['error' => $e->getMessage()], 400);
		} catch (SyncException $e) {
			return new JsonResponse(['error' => $e->getMessage(), 'details' => $e->details], $e->status);
		}
	}
}
