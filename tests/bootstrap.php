<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Deterministic environment: real variables win over site/.env (Config::loadDotEnv), so the suite never
// sees the production password hash, secret or data. Data and cache directories are set per test.
$testEnv = [
	// Password "tajne-heslo" (password_hash with a low cost keeps the suite fast).
	'SITE_PASSWORD_HASH' => password_hash('tajne-heslo', PASSWORD_BCRYPT, ['cost' => 4]),
	'SITE_SECRET' => 'test-secret-test-secret-test-secret-0123',
	'SITE_DEFAULT_PERSON' => '',
	'SITE_TRUST_PROXY' => '0',
	'SITE_LOGIN_DAYS' => '30',
	'SITE_FORCE_HTTPS' => '0',
	'SITE_SYNC_TOKEN' => '',
	'SITE_SEARCH_HINT' => '',
];
foreach ($testEnv as $name => $value) {
	putenv($name . '=' . $value);
}
