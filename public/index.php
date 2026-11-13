<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

Rodokmen\Site\Kernel::create(dirname(__DIR__))->run();
