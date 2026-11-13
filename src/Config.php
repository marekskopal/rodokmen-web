<?php

declare(strict_types=1);

namespace Rodokmen\Site;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

/**
 * Application configuration read from environment variables (or an optional .env file).
 */
final readonly class Config
{
	public function __construct(
		public string $rootDir,
		public string $dataDir,
		public string $cacheDir,
		public string $passwordHash,
		public string $secret,
		public ?string $defaultPerson,
		public bool $trustProxy,
		public int $loginDays,
		public bool $forceHttps,
		/** Bearer token of the data upload API (/api/sync/*); empty disables the API. */
		public string $syncToken = '',
		/** Example shown in the search field, e.g. "Např. Novák, Lomnice, tesař…". */
		public string $searchHint = '',
		/** Folder of log/app.log (SITE_LOG_DIR). */
		public string $logDir = '',
		/** Minimum PSR-3 level written to the log (SITE_LOG_LEVEL: debug, info, notice, warning, error). */
		public string $logLevel = 'notice',
	) {
	}

	public static function fromEnvironment(string $rootDir): self
	{
		self::loadDotEnv($rootDir . '/.env');

		// Quotes are stripped because `docker run --env-file` passes them literally
		// (the password hash must be single-quoted in .env so that `$` is not interpolated).
		$get = static fn (string $name, string $default = ''): string => trim(
			(string) (getenv($name) !== false ? getenv($name) : $default),
			" \t\"'",
		);

		$defaultPerson = trim($get('SITE_DEFAULT_PERSON'));

		return new self(
			rootDir: $rootDir,
			dataDir: rtrim($get('SITE_DATA_DIR', $rootDir . '/var/data'), '/'),
			cacheDir: rtrim($get('SITE_CACHE_DIR', $rootDir . '/var/cache'), '/'),
			passwordHash: trim($get('SITE_PASSWORD_HASH')),
			secret: trim($get('SITE_SECRET')),
			defaultPerson: $defaultPerson !== '' ? $defaultPerson : null,
			trustProxy: $get('SITE_TRUST_PROXY', '0') === '1',
			loginDays: max(1, (int) $get('SITE_LOGIN_DAYS', '30')),
			forceHttps: $get('SITE_FORCE_HTTPS', '1') !== '0',
			syncToken: $get('SITE_SYNC_TOKEN'),
			searchHint: $get('SITE_SEARCH_HINT'),
			logDir: rtrim($get('SITE_LOG_DIR', $rootDir . '/log'), '/'),
			logLevel: strtolower($get('SITE_LOG_LEVEL', 'notice')),
		);
	}

	/** Minimal .env loader: KEY=value lines, real environment variables take precedence. */
	private static function loadDotEnv(string $file): void
	{
		if (!is_file($file)) {
			return;
		}

		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines === false ? [] : $lines as $line) {
			$line = trim($line);
			if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
				continue;
			}

			[$name, $value] = array_map('trim', explode('=', $line, 2));
			$value = trim($value, "\"'");
			if (getenv($name) === false) {
				putenv($name . '=' . $value);
			}
		}
	}
}
