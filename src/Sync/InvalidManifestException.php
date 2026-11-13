<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use RuntimeException;

/** The uploaded manifest is not a valid data bundle description. */
final class InvalidManifestException extends RuntimeException
{
}
