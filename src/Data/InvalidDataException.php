<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use RuntimeException;

/** The exported site.json does not match the models (an exporter and website out of sync). */
final class InvalidDataException extends RuntimeException
{
}
