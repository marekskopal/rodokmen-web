<?php

declare(strict_types=1);

namespace Rodokmen\Site;

use InvalidArgumentException;
use Rodokmen\Site\Http\TrustedProxies;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Application configuration read from environment variables (or an optional .env file).
 */
final readonly class Config
{
	/** What SITE_TRUSTED_PROXIES=private (and the older SITE_TRUST_PROXY=1) stands for: loopback and private networks. */
	public const PrivateNetworks = ['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7'];

	public function __construct(
		public string $rootDir,
		public string $dataDir,
		public string $cacheDir,
		public string $passwordHash,
		public string $secret,
		public ?string $defaultPerson,
		/** @var list<string> addresses and ranges (CIDR) of the reverse proxies whose forwarded headers are believed */
		public array $trustedProxies,
		public int $loginDays,
		public bool $forceHttps,
		/** Bearer token of the data upload API (/api/sync/*); empty disables the API. */
		public string $syncToken = '',
		/** Example shown in the search field, e.g. "Např. Novák, Lomnice, tesař…". */
		public string $searchHint = '',
		/** Folder of the Tracy log files (SITE_LOG_DIR). */
		public string $logDir = '',
		/** SITE_LOG_LEVEL=debug also writes debug records. */
		public bool $logDebug = false,
		/** Copy every record to stderr (SITE_LOG_STDERR, default on) – Apache's error log, the container log in Docker. */
		public bool $logStderr = true,
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
			trustedProxies: self::trustedProxies(
				$get('SITE_TRUSTED_PROXIES'),
				getenv('SITE_TRUSTED_PROXIES') === false && $get('SITE_TRUST_PROXY', '0') === '1',
			),
			loginDays: max(1, (int) $get('SITE_LOGIN_DAYS', '30')),
			forceHttps: $get('SITE_FORCE_HTTPS', '1') !== '0',
			syncToken: $get('SITE_SYNC_TOKEN'),
			searchHint: $get('SITE_SEARCH_HINT'),
			logDir: rtrim($get('SITE_LOG_DIR', $rootDir . '/log'), '/'),
			logDebug: $get('SITE_LOG_LEVEL', 'production') === 'debug',
			logStderr: $get('SITE_LOG_STDERR', '1') !== '0',
		);
	}

	/**
	 * SITE_TRUSTED_PROXIES: addresses and ranges separated by commas or spaces, "private" for the private networks.
	 * When it is not set at all, the older SITE_TRUST_PROXY=1 means "private"; set but empty means no proxy.
	 *
	 * @return list<string>
	 */
	private static function trustedProxies(string $value, bool $trustPrivate): array
	{
		$entries = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
		$entries = $entries === false || $entries === [] ? ($trustPrivate ? ['private'] : []) : $entries;

		$ranges = [];
		foreach ($entries as $entry) {
			if (strtolower($entry) === 'private') {
				array_push($ranges, ...self::PrivateNetworks);
			} elseif (TrustedProxies::isValidRange($entry)) {
				$ranges[] = $entry;
			} else {
				throw new InvalidArgumentException(
					'SITE_TRUSTED_PROXIES: "' . $entry . '" is not an IP address or range (e.g. 10.0.0.0/8)',
				);
			}
		}

		return array_values(array_unique($ranges));
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
