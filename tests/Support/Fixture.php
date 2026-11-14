<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Support;

use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Auth\LoginThrottle;
use Rodokmen\Site\Auth\ThrottleCleanup;
use Rodokmen\Site\Config;
use Rodokmen\Site\Http\TrustedProxies;
use RuntimeException;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * Small exported tree for tests: Antonín (1) – his parents Jan (2) and Marie (3), Jan's father Josef (4),
 * one sibling and one other relative, one record, one line and one place. Written to a temporary data dir.
 */
final class Fixture
{
	/** @return array<string, mixed> site.json as exported by tools/export_web.py */
	public static function siteData(): array
	{
		return [
			'config' => ['nazev' => 'Rodokmen Testových', 'popis' => '', 'zapati' => 'Testovací patička', 'kontakt' => ['jmeno' => 'Markovi Testovi', 'email' => 'test@example.org']],
			'root' => 'antonin-novak-2024',
			'aliases' => ['stary-slug' => 'jan-novak-1990'],
			'stats' => ['ancestors' => 3, 'documented' => 2, 'records' => 1, 'generations' => 3],
			'people' => [
				'antonin-novak-2024' => self::person(
					1,
					'antonin-novak-2024',
					'Antonín Novák',
					'* 2024',
					0,
					father: 'jan-novak-1990',
					mother: 'marie-mala-1992',
					family: 'jan-novak-1990-a-marie-mala-1992',
					path: ['antonin-novak-2024'],
				),
				'jan-novak-1990' => self::person(
					2,
					'jan-novak-1990',
					'Jan Novák',
					'1990 – ?',
					1,
					father: 'josef-novak-1960',
					child: 'antonin-novak-2024',
					spouse: 'marie-mala-1992',
					family: 'josef-novak-1960-rodina',
					ownFamily: 'jan-novak-1990-a-marie-mala-1992',
					path: ['jan-novak-1990', 'antonin-novak-2024'],
					side: [
						['name' => 'Petr Novák (* 1993)', 'text' => 'Mladší bratr Jana.', 'records' => [], 'rel' => 'sourozenec', 'y' => '1993', 'unsure' => false],
						['name' => 'Eva Nová (* 1985)', 'text' => 'Nevlastní sestra.', 'records' => [], 'rel' => 'nevlastní sourozenec', 'y' => '1985', 'unsure' => false],
						['name' => 'Karel Novák', 'text' => 'Bratranec.', 'records' => [], 'rel' => '', 'y' => '', 'unsure' => false],
					],
					records: ['praha_krest_jan_novak_1990'],
				),
				'marie-mala-1992' => self::person(
					3,
					'marie-mala-1992',
					'Marie Malá',
					'1992 – ?',
					1,
					sex: 'F',
					child: 'antonin-novak-2024',
					spouse: 'jan-novak-1990',
					ownFamily: 'jan-novak-1990-a-marie-mala-1992',
					path: ['marie-mala-1992', 'antonin-novak-2024'],
				),
				'josef-novak-1960' => self::person(
					4,
					'josef-novak-1960',
					'Josef Novák',
					'1960 – 2020',
					2,
					child: 'jan-novak-1990',
					ownFamily: 'josef-novak-1960-rodina',
					path: ['josef-novak-1960', 'jan-novak-1990', 'antonin-novak-2024'],
				),
			],
			'families' => [
				'jan-novak-1990-a-marie-mala-1992' => [
					'id' => 'jan-novak-1990-a-marie-mala-1992',
					'father' => 'jan-novak-1990',
					'mother' => 'marie-mala-1992',
					'fatherUnknown' => false,
					'child' => 'antonin-novak-2024',
					'children' => ['antonin-novak-2024'],
					'marriage' => [],
					'records' => [],
					'line' => 8,
					'gen' => 1,
					'side' => [],
				],
				'josef-novak-1960-rodina' => [
					'id' => 'josef-novak-1960-rodina',
					'father' => 'josef-novak-1960',
					'mother' => null,
					'fatherUnknown' => false,
					'child' => 'jan-novak-1990',
					'children' => ['jan-novak-1990'],
					'marriage' => [],
					'records' => [],
					'line' => 8,
					'gen' => 2,
					'side' => [
						['name' => 'Petr Novák (* 1993)', 'text' => 'Mladší bratr Jana.', 'records' => [], 'rel' => 'sourozenec', 'y' => '1993', 'unsure' => false],
						['name' => 'Eva Nová (* 1985)', 'text' => 'Nevlastní sestra.', 'records' => [], 'rel' => 'nevlastní sourozenec', 'y' => '1985', 'unsure' => false],
					],
				],
			],
			'records' => [
				[
					'key' => 'praha_krest_jan_novak_1990',
					'kind' => 'N',
					'date' => '1. 5. 1990',
					'title' => 'Křest Jana Nováka',
					'summary' => 'Jan, syn Josefa Nováka.',
					'scan' => 12,
					'archive' => 'Archiv hl. m. Prahy',
					'volume' => 'N 1990',
					'vdesc' => 'N Praha 1990',
					'portal' => 'eBadatelna',
					'url' => 'https://example.org/scan/12',
					'img' => false,
					'full' => false,
					'boxes' => [[0.1, 0.2, 0.9, 0.5]],
					'orig' => 'Joannes',
					'cs' => 'Jan',
					'kindName' => 'Křest',
					'people' => ['jan-novak-1990'],
				]],
			'lines' => [
				'novakovi' => [
					'k' => 8,
					'slug' => 'novakovi',
					'name' => 'Novákovi',
					'desc' => 'Pražský rod.',
					'color' => '#336699',
					'root' => 'josef-novak-1960',
					'people' => ['jan-novak-1990', 'josef-novak-1960'],
					'gens' => ['jan-novak-1990' => 1, 'josef-novak-1960' => 2],
				],
			],
			'places' => [
				[
					'name' => 'Praha',
					'lat' => 50.08,
					'lon' => 14.43,
					'lines' => [['k' => 8, 'people' => ['jan-novak-1990', 'josef-novak-1960']], ['k' => 0, 'people' => ['antonin-novak-2024']]],
					'years' => [1960, 2024],
					'hypothesis' => false,
				]],
			'issues' => [['title' => 'Kdo byla matka Josefa', 'text' => 'Nevíme.'], ['title' => 'Datum křtu', 'text' => 'Vyřešeno: 1. 5. 1990.']],
			'hypotheses' => [
				[
					'line' => 8,
					'title' => 'Odkud byl Josef',
					'text' => 'Možná z Brna.',
					'items' => [['name' => 'Josef Novák', 'text' => 'kandidát']],
					'records' => [],
				]],
			'docs' => [['img' => 'foto_josef', 'caption' => 'Josef na vojně', 'people' => ['josef-novak-1960']]],
		];
	}

	/**
	 * Temporary data directory with site.json and markdown notes; returns the path.
	 *
	 * @param array<string, mixed>|null $siteData
	 */
	public static function dataDir(?array $siteData = null): string
	{
		$dir = sys_get_temp_dir() . '/rodokmen-test-' . bin2hex(random_bytes(6));
		mkdir($dir . '/md', 0777, true);
		file_put_contents($dir . '/site.json', json_encode($siteData ?? self::siteData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
		file_put_contents($dir . '/md/zajimavosti.md', "# Zajímavosti\n\n## Dva Augustinové\nText.\n\n## Narozen na Boží hod\nText.\n");

		return $dir;
	}

	/** @param list<string> $trustedProxies */
	public static function config(
		?string $dataDir = null,
		?string $defaultPerson = null,
		array $trustedProxies = [],
		bool $forceHttps = false,
		string $syncToken = '',
		string $searchHint = '',
	): Config
	{
		$dataDir ??= self::dataDir();

		return new Config(
			rootDir: dirname(__DIR__, 2),
			dataDir: $dataDir,
			cacheDir: $dataDir . '/cache',
			passwordHash: (string) getenv('SITE_PASSWORD_HASH'),
			secret: (string) getenv('SITE_SECRET'),
			defaultPerson: $defaultPerson,
			trustedProxies: $trustedProxies,
			loginDays: 30,
			forceHttps: $forceHttps,
			syncToken: $syncToken,
			searchHint: $searchHint,
		);
	}

	public static function auth(Config $config): Auth
	{
		return new Auth($config, new TrustedProxies($config));
	}

	/** $request with the login cookie that Auth issues for $config. */
	public static function logIn(ServerRequestInterface $request, Config $config): ServerRequestInterface
	{
		if (preg_match('/^' . Auth::CookieName . '=([^;]+);/', self::auth($config)->loginCookie($request), $m) !== 1) {
			throw new RuntimeException('Auth issued no login cookie');
		}

		return $request->withCookieParams([Auth::CookieName => rawurldecode($m[1])]);
	}

	public static function throttle(Config $config): LoginThrottle
	{
		return new LoginThrottle($config, new TrustedProxies($config), new ThrottleCleanup($config));
	}

	public static function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		$items = scandir($dir);
		foreach ($items === false ? [] : array_diff($items, ['.', '..']) as $item) {
			$path = $dir . '/' . $item;
			is_dir($path) && !is_link($path) ? self::removeDir($path) : unlink($path);
		}
		rmdir($dir);
	}

	/**
	 * @param list<string> $path
	 * @param list<array<string, mixed>> $side
	 * @param list<string> $records
	 * @return array<string, mixed>
	 */
	private static function person(
		int $k,
		string $slug,
		string $name,
		string $y,
		int $gen,
		string $sex = 'M',
		?string $father = null,
		?string $mother = null,
		?string $child = null,
		?string $spouse = null,
		?string $family = null,
		?string $ownFamily = null,
		array $path = [],
		array $side = [],
		array $records = [],
	): array {
		return [
			'k' => $k,
			'slug' => $slug,
			'name' => $name,
			'y' => $y,
			'years' => $y,
			'st' => $k === 1 ? 'fam' : 'doc',
			'status' => '',
			'gen' => $gen,
			'sex' => $sex,
			'rel' => $k === 1 ? 'výchozí osoba' : ($k < 4 ? ($sex === 'F' ? 'matka' : 'otec') : 'dědeček'),
			'line' => $k === 1 ? null : 8,
			'facts' => $k === 2 ? ['* 1. 5. 1990 Praha', '× 2015 Praha, Marie Malá'] : [],
			'note' => '',
			'records' => $records,
			'father' => $father,
			'mother' => $mother,
			'fatherUnknown' => false,
			'motherUnknown' => false,
			'child' => $child,
			'spouse' => $spouse,
			'family' => $family,
			'ownFamily' => $ownFamily,
			'path' => $path,
			'side' => $side,
			'docs' => [],
			'children' => $child !== null ? [$child] : [],
			'also' => [],
			'lineSlug' => $k === 1 ? null : 'novakovi',
		];
	}
}
