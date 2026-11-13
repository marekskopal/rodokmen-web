<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

/** One file of a data bundle: its SHA-256 and size in bytes. */
final readonly class ManifestFile
{
	public function __construct(public string $sha256, public int $size)
	{
	}
}
