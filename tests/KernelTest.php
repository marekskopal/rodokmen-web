<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rodokmen\Site\Auth\Auth;
use Rodokmen\Site\Auth\ThrottleScope;
use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Kernel;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\Tests\Support\Images;
use const JSON_THROW_ON_ERROR;
use const PHP_URL_QUERY;

/**
 * Whole application with the fixture data: routes, templates, login and error handling.
 */
final class KernelTest extends TestCase
{
	private string $dir;

	private Kernel $kernel;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		putenv('SITE_DATA_DIR=' . $this->dir);
		putenv('SITE_CACHE_DIR=' . $this->dir . '/cache');
		$this->kernel = Kernel::create(dirname(__DIR__));
	}

	protected function tearDown(): void
	{
		Fixture::removeDir((string) getenv('SITE_LOG_DIR'));
		putenv('SITE_DATA_DIR');
		putenv('SITE_CACHE_DIR');
		Fixture::removeDir($this->dir);
	}

	/** @return iterable<string, array{string, list<string>}> */
	public static function pages(): iterable
	{
		yield 'home' => ['/', ['Rodokmen Testových', 'Novákovi', 'Dva Augustinové']];
		yield 'explorer' => ['/predci', ['Prohlížeč předků', 'Jan Novák', 'Marie Malá']];
		yield 'explorer of person' => ['/predci/jan-novak-1990', ['Josef Novák']];
		yield 'tree' => ['/strom/antonin-novak-2024', ['Celý strom předků', 'Josef Novák']];
		yield 'fan' => ['/vejir/antonin-novak-2024', ['Vějíř předků', 'href="/osoba/josef-novak-1960"', 'class="fan-empty"']];
		yield 'fan with depth' => ['/vejir?generace=1', ['Vějíř předků', 'Marie Malá']];
		yield 'person' => ['/osoba/jan-novak-1990', ['Sourozenci', 'Petr Novák (* 1993)', '· nevlastní', 'Bratranec.', 'Křest Jana Nováka']];
		yield 'grandfather lists siblings among children' => ['/osoba/josef-novak-1960', ['Děti', 'Petr Novák (* 1993)']];
		yield 'generations' => ['/generace', ['Generace', 'Josef Novák']];
		yield 'map' => ['/mapa', ['Praha', 'Nejbližší rodina']];
		yield 'records' => ['/zapisy', ['Křest Jana Nováka']];
		yield 'record' => ['/zapis/praha_krest_jan_novak_1990', ['Archiv hl. m. Prahy', 'Přepis originálu']];
		yield 'documents' => ['/dokumenty', ['Josef na vojně']];
		yield 'questions' => ['/hledame', ['Kdo byla matka Josefa', 'Odkud byl Josef', 'Vyřešené otázky (1)']];
		yield 'note' => ['/zajimavosti', ['Narozen na Boží hod']];
		yield 'search' => ['/hledat?q=Praha', ['value="Praha"']];
	}

	public function testMissingDataIsLoggedAndReportedAs503(): void
	{
		Fixture::removeDir($this->dir);

		$response = $this->get('/', loggedIn: true);

		self::assertSame(503, $response->getStatusCode());
		self::assertStringContainsString('Chybí data rodokmenu', (string) $response->getBody());
		self::assertStringContainsString('RuntimeException: Data not found', $this->appLog('error'));
	}

	public function testBrokenDataStillGivesAnErrorPage(): void
	{
		file_put_contents($this->dir . '/site.json', '{"broken": true}');

		$response = $this->get('/', loggedIn: true);

		self::assertSame(500, $response->getStatusCode());
		self::assertStringContainsString('Něco se pokazilo', (string) $response->getBody());
		self::assertStringContainsString('Cannot render the error page', $this->appLog('critical'));
	}

	public function testWrongPasswordIsLogged(): void
	{
		$this->post('/prihlaseni', ['heslo' => 'spatne']);

		self::assertStringContainsString('Wrong password', $this->appLog('warning'));
		self::assertStringContainsString('127.0.0.1', $this->appLog('warning'));
	}

	/** @param list<string> $expected */
	#[DataProvider('pages')]
	public function testPageRenders(string $uri, array $expected): void
	{
		$response = $this->get($uri, loggedIn: true);
		$body = (string) $response->getBody();

		self::assertSame(200, $response->getStatusCode(), $body);
		foreach ($expected as $text) {
			self::assertStringContainsString($text, $body);
		}
		self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
	}

	public function testServesWebpThumbnails(): void
	{
		Images::jpeg($this->dir . '/img/krest.jpg', 1200, 800);

		$response = $this->get('/obrazek/nahled/480/krest.webp', loggedIn: true);
		self::assertSame(200, $response->getStatusCode());
		self::assertSame('image/webp', $response->getHeaderLine('Content-Type'));
		self::assertSame('private, max-age=86400', $response->getHeaderLine('Cache-Control'));
		$size = getimagesizefromstring((string) $response->getBody());
		self::assertSame([480, 320], [$size[0] ?? null, $size[1] ?? null]);

		$cached = $this->kernel->handle(
			$this->request('GET', '/obrazek/nahled/480/krest.webp', true)->withHeader('If-None-Match', $response->getHeaderLine('ETag')),
		);
		self::assertSame(304, $cached->getStatusCode());

		self::assertSame(302, $this->get('/obrazek/nahled/480/krest.webp')->getStatusCode(), 'only for logged-in visitors');
		self::assertSame(404, $this->get('/obrazek/nahled/500/krest.webp', loggedIn: true)->getStatusCode(), 'only the fixed widths');
		self::assertSame(404, $this->get('/obrazek/nahled/480/chybi.webp', loggedIn: true)->getStatusCode());
		self::assertSame(404, $this->get('/obrazek/nahled/480/krest.jpg', loggedIn: true)->getStatusCode());
		self::assertSame(404, $this->get('/obrazek/nahled/480/..%2Fkrest.webp', loggedIn: true)->getStatusCode());
	}

	public function testThumbnailFallsBackToTheJpeg(): void
	{
		// A source GD cannot read gets no WebP copy: the JPEG is served instead of a broken image.
		mkdir($this->dir . '/img');
		file_put_contents($this->dir . '/img/krest.jpg', 'not really a jpeg');

		$response = $this->get('/obrazek/nahled/960/krest.webp', loggedIn: true);
		self::assertSame(200, $response->getStatusCode());
		self::assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));
		self::assertSame('not really a jpeg', (string) $response->getBody());
	}

	public function testFooterHasDisclaimerWithContact(): void
	{
		$body = (string) $this->get('/', loggedIn: true)->getBody();

		self::assertStringContainsString('mohou obsahovat chyby', $body);
		self::assertStringContainsString('napište Markovi Testovi', $body);
		self::assertStringContainsString('href="mailto:test@example.org"', $body);
	}

	public function testDisclaimerWithoutContact(): void
	{
		$data = Fixture::siteData();
		$data['config'] = ['nazev' => 'Bez kontaktu'];
		file_put_contents($this->dir . '/site.json', json_encode($data, JSON_THROW_ON_ERROR));

		$body = (string) $this->get('/', loggedIn: true)->getBody();

		self::assertStringContainsString('mohou obsahovat chyby', $body);
		self::assertStringNotContainsString('mailto:', $body);
	}

	public function testHalfSiblingIsNotAChildOfTheGrandparents(): void
	{
		$body = (string) $this->get('/osoba/josef-novak-1960', loggedIn: true)->getBody();

		self::assertStringNotContainsString('Eva Nová', $body);
	}

	public function testSiblingsAreNotDuplicated(): void
	{
		$body = (string) $this->get('/osoba/jan-novak-1990', loggedIn: true)->getBody();

		self::assertSame(1, substr_count($body, '<li><strong>Petr Novák (* 1993)</strong>'));
	}

	public function testSearchIndex(): void
	{
		$response = $this->get('/data/hledani.json', loggedIn: true);
		$items = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

		self::assertIsArray($items);
		self::assertCount(4 + 1 + 1, $items);
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
	}

	public function testAliasRedirectsToCurrentSlug(): void
	{
		$response = $this->get('/osoba/stary-slug', loggedIn: true);

		self::assertSame(301, $response->getStatusCode());
		self::assertSame('/osoba/jan-novak-1990', $response->getHeaderLine('Location'));
	}

	public function testForwardedHeadersDoNotRewriteTheRequest(): void
	{
		$request = Kernel::requestFromGlobals([
			'REMOTE_ADDR' => '172.17.0.1',
			'HTTP_HOST' => 'rodokmen.example',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTP_X_FORWARDED_HOST' => 'evil.example',
			'REQUEST_URI' => '/prihlaseni',
			'REQUEST_METHOD' => 'GET',
		]);

		self::assertSame('http', $request->getUri()->getScheme());
		self::assertSame('rodokmen.example', $request->getUri()->getHost());
		self::assertSame('https', $request->getHeaderLine('X-Forwarded-Proto'), 'left for TrustedProxies to decide');
	}

	public function testMapUsesTheBundledLeaflet(): void
	{
		// The query in the layout has to follow the version installed by pnpm (package.json).
		$package = json_decode((string) file_get_contents(dirname(__DIR__) . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($package);
		self::assertIsArray($package['dependencies']);
		$version = $package['dependencies']['leaflet'];
		self::assertIsString($version);

		$html = (string) $this->get('/mapa', loggedIn: true)->getBody();

		self::assertStringContainsString('src="/assets/vendor/leaflet/leaflet.js?v=' . $version . '"', $html);
		self::assertStringContainsString('href="/assets/vendor/leaflet/leaflet.css?v=' . $version . '"', $html);
		self::assertStringNotContainsString('cdnjs', $html);
	}

	public function testHealthCheckNeedsNoLogin(): void
	{
		$response = $this->get('/health');

		self::assertSame(200, $response->getStatusCode());
		self::assertSame(['status' => 'ok'], json_decode((string) $response->getBody(), true));
		self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
	}

	public function testRoutesAreCachedUntilTheControllersChange(): void
	{
		$cache = $this->dir . '/cache/routes/routes.php';
		self::assertSame(200, $this->get('/health')->getStatusCode());
		self::assertFileExists($cache);

		// A cached route table is used as it is: an entry pointing /health elsewhere shows it is read.
		$entry = include $cache;
		self::assertIsArray($entry);
		$entry['value'] = serialize([]);
		file_put_contents($cache, '<?php return ' . var_export($entry, true) . ';');
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($cache, true);
		}
		self::assertSame('/prihlaseni', $this->get('/health')->getHeaderLine('Location'));

		// Another code version (here: other controllers' modification times) rebuilds it.
		$entry['version'] = 'older code';
		file_put_contents($cache, '<?php return ' . var_export($entry, true) . ';');
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($cache, true);
		}
		self::assertSame(200, $this->get('/health')->getStatusCode());
	}

	public function testUnknownPages(): void
	{
		self::assertSame(404, $this->get('/osoba/nikdo', loggedIn: true)->getStatusCode());
		self::assertSame(404, $this->get('/neexistuje', loggedIn: true)->getStatusCode());
		self::assertSame('/prihlaseni', $this->get('/neexistuje')->getHeaderLine('Location'));
	}

	public function testAnonymousVisitorMustLogIn(): void
	{
		$response = $this->get('/osoba/jan-novak-1990');

		self::assertSame(302, $response->getStatusCode());
		self::assertStringStartsWith('/prihlaseni?k=', $response->getHeaderLine('Location'));
		self::assertSame(200, $this->get('/prihlaseni')->getStatusCode());
	}

	public function testLogin(): void
	{
		$wrong = $this->post('/prihlaseni', ['heslo' => 'spatne', 'k' => '/mapa']);
		self::assertSame(401, $wrong->getStatusCode());
		self::assertStringContainsString('Heslo nesouhlasí', (string) $wrong->getBody());

		$ok = $this->post('/prihlaseni', ['heslo' => 'tajne-heslo', 'k' => '/mapa']);
		self::assertSame(302, $ok->getStatusCode());
		self::assertSame('/mapa', $ok->getHeaderLine('Location'));
		self::assertStringStartsWith(Auth::CookieName . '=', $ok->getHeaderLine('Set-Cookie'));
	}

	public function testKnownDeviceCanLogInDuringAnAttack(): void
	{
		$ok = $this->post('/prihlaseni', ['heslo' => 'tajne-heslo']);
		$device = null;
		foreach ($ok->getHeader('Set-Cookie') as $cookie) {
			if (preg_match('/^' . Auth::DeviceCookieName . '=([^;]+);/', $cookie, $m) === 1) {
				$device = rawurldecode($m[1]);
			}
		}
		self::assertNotNull($device, 'a successful login marks the browser');

		// 50 wrong passwords from many addresses block new browsers…
		$throttle = Fixture::throttle(Fixture::config($this->dir));
		for ($i = 0; $i < 50; $i++) {
			$throttle->registerFailure(new ServerRequest(['REMOTE_ADDR' => '198.51.100.' . $i]), ThrottleScope::Login);
		}
		self::assertSame(429, $this->post('/prihlaseni', ['heslo' => 'tajne-heslo'])->getStatusCode());

		// …but not a browser that has logged in before.
		$request = $this->request('POST', '/prihlaseni', false)
			->withParsedBody(['heslo' => 'tajne-heslo'])
			->withCookieParams([Auth::DeviceCookieName => $device]);
		self::assertSame(302, $this->kernel->handle($request)->getStatusCode());
	}

	public function testLoginTargetMustBeLocal(): void
	{
		$response = $this->post('/prihlaseni', ['heslo' => 'tajne-heslo', 'k' => 'https://evil.example/']);

		self::assertSame('/', $response->getHeaderLine('Location'));
	}

	public function testSetHomePerson(): void
	{
		$response = $this->post('/vychozi/josef-novak-1960', [], loggedIn: true);

		self::assertSame(302, $response->getStatusCode());
		self::assertStringStartsWith(HomePerson::CookieName . '=josef-novak-1960;', $response->getHeaderLine('Set-Cookie'));
		self::assertStringStartsWith(
			HomePerson::CookieName . '=;',
			$this->post('/vychozi/antonin-novak-2024', [], loggedIn: true)->getHeaderLine('Set-Cookie'),
		);
	}

	private function appLog(string $level): string
	{
		return (string) @file_get_contents((string) getenv('SITE_LOG_DIR') . '/' . $level . '.log');
	}

	private function get(string $uri, bool $loggedIn = false): ResponseInterface
	{
		return $this->kernel->handle($this->request('GET', $uri, $loggedIn));
	}

	/** @param array<string, string> $body */
	private function post(string $uri, array $body, bool $loggedIn = false): ResponseInterface
	{
		return $this->kernel->handle($this->request('POST', $uri, $loggedIn)->withParsedBody($body));
	}

	private function request(string $method, string $uri, bool $loggedIn): ServerRequestInterface
	{
		parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
		$request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://localhost' . $uri, $method)->withQueryParams($query);
		if (!$loggedIn) {
			return $request;
		}

		return Fixture::logIn($request, Fixture::config($this->dir));
	}
}
