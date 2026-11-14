<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Rodokmen\Site\Auth\LoginThrottle;
use Rodokmen\Site\Auth\ThrottleScope;
use Rodokmen\Site\Config;
use Rodokmen\Site\Sync\DataStore;
use Rodokmen\Site\Sync\InvalidManifestException;
use Rodokmen\Site\Sync\Manifest;
use Rodokmen\Site\Sync\SyncException;

/**
 * Data upload API used by bin/rodokmen-sync (enabled by SITE_SYNC_TOKEN):
 *  1. POST /api/sync/plan manifest.json → hashes of the files the server is missing
 *     (and whether the website already shows this bundle),
 *  2. PUT /api/sync/file/{sha256} raw file content, once per missing hash,
 *  3. POST /api/sync/commit manifest.json → the new data is published atomically.
 * POST /api/sync/rollback switches the website back to the previous release.
 */
final readonly class SyncController
{
	public const TokenHeader = 'X-Sync-Token';

	public function __construct(
		private Config $config,
		private DataStore $store,
		private LoginThrottle $throttle,
		private LoggerInterface $logger,
	) {
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
			$this->logger->info('Data published', ['release' => $release, 'files' => count($manifest->files)]);

			return new JsonResponse(['release' => $release, 'files' => count($manifest->files)]);
		});
	}

	#[RoutePost('/api/sync/rollback')]
	public function rollback(ServerRequestInterface $request): ResponseInterface
	{
		return $this->guarded($request, function (): JsonResponse {
			$release = $this->store->rollback();
			$this->logger->info('Data rolled back', ['release' => $release]);

			return new JsonResponse(['release' => $release]);
		});
	}

	/** @param callable(): JsonResponse $action */
	private function guarded(ServerRequestInterface $request, callable $action): ResponseInterface
	{
		if ($this->config->syncToken === '') {
			return new JsonResponse(['error' => 'The sync API is disabled (set SITE_SYNC_TOKEN)'], 404);
		}

		$wait = $this->throttle->retryAfter($request, ThrottleScope::Sync);
		if ($wait > 0) {
			return (new JsonResponse(['error' => 'Too many failed attempts'], 429))->withHeader('Retry-After', (string) $wait);
		}

		$token = $this->token($request);
		if ($token === null || !hash_equals($this->config->syncToken, $token)) {
			$this->throttle->registerFailure($request, ThrottleScope::Sync);
			$this->logger->warning('Sync API: invalid token', ['ip' => $this->throttle->clientIp($request)]);

			return new JsonResponse(['error' => 'Invalid token'], 401);
		}

		try {
			return $action();
		} catch (InvalidManifestException $e) {
			$this->logger->warning('Sync API: ' . $e->getMessage());

			return new JsonResponse(['error' => $e->getMessage()], 400);
		} catch (SyncException $e) {
			$this->logger->warning('Sync API: ' . $e->getMessage(), ['status' => $e->status]);

			return new JsonResponse(['error' => $e->getMessage(), 'details' => $e->details], $e->status);
		}
	}

	/**
	 * Token from "Authorization: Bearer …", or from X-Sync-Token on servers that do not pass the Authorization
	 * header to PHP (Apache with PHP-FPM without the rewrite rule in public/.htaccess).
	 */
	private function token(ServerRequestInterface $request): ?string
	{
		if (preg_match('/^Bearer\s+(\S+)$/', $request->getHeaderLine('Authorization'), $m) === 1) {
			return $m[1];
		}

		$token = $request->getHeaderLine(self::TokenHeader);

		return $token !== '' ? $token : null;
	}
}
