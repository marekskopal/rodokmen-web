<?php

declare(strict_types=1);

namespace Rodokmen\Site\Cache;

use InvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

final class InvalidCacheKeyException extends InvalidArgumentException implements SimpleCacheInvalidArgumentException
{
}
