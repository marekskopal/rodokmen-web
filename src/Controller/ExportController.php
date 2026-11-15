<?php

declare(strict_types=1);

namespace Rodokmen\Site\Controller;

use DateTimeImmutable;
use Laminas\Diactoros\Response\TextResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Rodokmen\Site\Export\Gedcom;

/**
 * Download of the family tree for other genealogy software (logged-in visitors only, like everything else).
 */
final readonly class ExportController
{
	public function __construct(private Gedcom $gedcom)
	{
	}

	#[RouteGet('/export/rodokmen.ged')]
	public function gedcom(): ResponseInterface
	{
		return new TextResponse($this->gedcom->export(new DateTimeImmutable()), 200, [
			'Content-Type' => 'text/vnd.familysearch.gedcom; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="rodokmen.ged"',
		]);
	}
}
