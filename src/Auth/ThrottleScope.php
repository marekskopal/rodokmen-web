<?php

declare(strict_types=1);

namespace Rodokmen\Site\Auth;

/** What LoginThrottle counts failures for; every scope has its own counters. */
enum ThrottleScope: string
{
	case Login = 'login';
	case Sync = 'sync';
}
