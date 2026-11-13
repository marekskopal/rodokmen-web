<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Config;

/**
 * The person the ancestor explorer starts from: a per-visitor choice (cookie),
 * else SITE_DEFAULT_PERSON, else the root of the exported tree.
 */
final readonly class HomePerson
{
	public const CookieName = 'rk_home';

	public function __construct(private Repository $repository, private Config $config)
	{
	}

	public function slug(ServerRequestInterface $request): string
	{
		$chosen = $request->getCookieParams()[self::CookieName] ?? null;
		if (is_string($chosen) && $this->repository->person($chosen) !== null) {
			return $chosen;
		}

		return $this->defaultSlug();
	}

	public function defaultSlug(): string
	{
		if ($this->config->defaultPerson !== null && $this->repository->person($this->config->defaultPerson) !== null) {
			return $this->config->defaultPerson;
		}

		return $this->repository->rootSlug();
	}

	public function isCustom(ServerRequestInterface $request): bool
	{
		return $this->slug($request) !== $this->defaultSlug();
	}
}
