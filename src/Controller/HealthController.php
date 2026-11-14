<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;

/**
 * Health check for Docker's HEALTHCHECK and monitoring: answers without a login and tells nothing about the family
 * tree – only that PHP and the application run.
 */
final readonly class HealthController
{
	#[RouteGet('/health')]
	public function health(): ResponseInterface
	{
		return new JsonResponse(['status' => 'ok']);
	}
}
