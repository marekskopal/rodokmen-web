<?php

declare(strict_types=1);

// Router for the PHP built-in server (local development only):
//   php -S localhost:8080 -t public bin/dev-router.php
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);
if (is_string($path) && $path !== '/' && is_file(__DIR__ . '/../public' . $path)) {
	return false;
}

require __DIR__ . '/../public/index.php';
