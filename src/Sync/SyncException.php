<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use RuntimeException;

/** A data upload that cannot be accepted; carries the HTTP status and optional details for the client. */
final class SyncException extends RuntimeException
{
	/** @param list<string> $details */
	public function __construct(string $message, public readonly int $status, public readonly array $details = [])
	{
		parent::__construct($message);
	}
}
