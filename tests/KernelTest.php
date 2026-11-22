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
use const JSON_UNESCAPED_UNICODE;
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
		yield 'home' => ['/', ['Rodokmen Testových', 'Novákovi', 'Dva Augustinové', 'href="/rod/novakovi"', 'Přečíst příběh Nováků →', 'href="/pribehy"', 'Další příběhy rodů', 'href="/pribeh/malovi"']];
		yield 'story' => [
			'/pribeh/novakovi',
			[
				'<h1>Příběh Nováků</h1>',
				'Od Josefa k Antonínovi',
				'href="/osoba/josef-novak-1960"',
				'href="/zapis/praha_krest_jan_novak_1990"',
				'Rod Novákovi',
			]];

		yield 'line story' => ['/pribeh/malovi', ['<h1>Příběh Malých</h1>', 'Marie a její předci.', 'href="/osoba/marie-mala-1992"', 'Rod Malí', 'href="/pribehy"']];
		yield 'stories' => ['/pribehy', ['Příběhy rodů', 'Příběh Nováků', 'href="/pribeh/novakovi"', 'Příběh Malých', 'Marie a její předci.']];
		yield 'line with its story' => ['/rod/malovi', ['Malí', 'Příběh Malých', 'href="/pribeh/malovi"']];
		yield 'line' => ['/rod/novakovi', ['Novákovi', 'Pražský rod.', '2 osoby', 'Příběh Nováků', 'Josef Novák', 'Prarodiče', 'href="/predci/josef-novak-1960"']];
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

	public function testGedcomDownload(): void
	{
		$response = $this->get('/export/rodokmen.ged', loggedIn: true);
		$body = (string) $response->getBody();

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('text/vnd.familysearch.gedcom; charset=utf-8', $response->getHeaderLine('Content-Type'));
		self::assertSame('attachment; filename="rodokmen.ged"', $response->getHeaderLine('Content-Disposition'));
		self::assertStringStartsWith("\u{FEFF}0 HEAD\r\n", $body);
		self::assertStringContainsString('1 NAME Jan /Novák/', $body);
		self::assertSame(302, $this->get('/export/rodokmen.ged')->getStatusCode(), 'only for logged-in visitors');
		self::assertStringContainsString('href="/export/rodokmen.ged"', (string) $this->get('/', loggedIn: true)->getBody());
	}

	public function testPrintsTheNumberedAncestors(): void
	{
		$response = $this->get('/predci/antonin-novak-2024/tisk', loggedIn: true);
		$body = (string) $response->getBody();

		self::assertSame(200, $response->getStatusCode(), $body);
		self::assertStringContainsString('Vývod předků', $body);
		self::assertMatchesRegularExpression('/\s2 generace předků,\s+3 známí předkové\s/u', $body);
		self::assertStringContainsString('id="c-4"', $body, 'grandfather Josef Novák');
		self::assertStringNotContainsString('id="c-5"', $body, 'the unknown grandmother has no entry');
		self::assertStringContainsString('<a href="#c-2">č. 2</a> Jan Novák', $body);
		self::assertStringContainsString('N Praha 1990, snímek 12', $body);
		self::assertStringNotContainsString('Joannes', $body, 'transcriptions only on request');
		self::assertStringContainsString('data-print', $body);

		$withTexts = (string) $this->get('/predci/antonin-novak-2024/tisk?zapisy=1', loggedIn: true)->getBody();
		self::assertStringContainsString('Joannes', $withTexts);

		$oneGeneration = (string) $this->get('/predci/antonin-novak-2024/tisk?generace=1', loggedIn: true)->getBody();
		self::assertStringContainsString('id="c-3"', $oneGeneration);
		self::assertStringNotContainsString('id="c-4"', $oneGeneration);
		self::assertStringContainsString('<option value="1" selected>', $oneGeneration);
		self::assertStringContainsString('Otec: Josef Novák', $oneGeneration, 'a parent beyond the limit is named, without a number');
		self::assertStringNotContainsString('#c-4', $oneGeneration);

		self::assertSame(404, $this->get('/predci/nikdo/tisk', loggedIn: true)->getStatusCode());
		self::assertSame(302, $this->get('/predci/antonin-novak-2024/tisk')->getStatusCode());
	}

	public function testDrawsTheAncestorPoster(): void
	{
		$response = $this->get('/predci/antonin-novak-2024/plakat?styl=jednoduchy', loggedIn: true);
		$body = (string) $response->getBody();

		self::assertSame(200, $response->getStatusCode(), $body);
		self::assertStringContainsString('Plakát předků', $body);
		self::assertStringContainsString('class="poster-sheet poster-a1-landscape"', $body);
		self::assertStringContainsString('width="841mm" height="594mm"', $body);
		self::assertStringContainsString('<option value="" selected>doporučeno (2)</option>', $body);
		self::assertStringContainsString('Josef Novák', $body);
		self::assertStringContainsString('data-print', $body);

		$query = '?rozvrzeni=pulvejir&format=a3&orientace=na-vysku&generace=1&styl=jednoduchy&nadpis=Rod+Nov%C3%A1k%C5%AF';
		$chosen = (string) $this->get('/predci/antonin-novak-2024/plakat' . $query, loggedIn: true)->getBody();
		self::assertStringContainsString('class="poster-sheet poster-a3-portrait"', $chosen);
		self::assertStringContainsString('<option value="pulvejir" selected>', $chosen);
		self::assertStringContainsString('<option value="1" selected>1</option>', $chosen);
		self::assertStringContainsString('value="Rod Nováků"', $chosen);
		self::assertStringContainsString('>Rod Nováků</text>', $chosen);
		self::assertStringNotContainsString('Josef Novák', $chosen);

		// The site's title heads the poster of its own person; anybody else gets their family. (In the simple style,
		// whose heading has no illuminated initial.)
		self::assertStringContainsString('>Rodokmen Testových</text>', $body);
		self::assertStringContainsString('placeholder="Rodokmen Testových"', $body);
		$mother = (string) $this->get('/predci/marie-mala-1992/plakat?styl=jednoduchy', loggedIn: true)->getBody();
		self::assertStringContainsString('class="pst-t pst-title"', $mother);
		self::assertMatchesRegularExpression('/class="pst-t pst-title"[^>]*>Rodokmen Malých<\/text>/', $mother);
		self::assertStringContainsString('placeholder="Rodokmen Malých"', $mother);
		self::assertStringContainsString(
			'>Rodokmen Malých</text>',
			(string) $this->get('/predci/marie-mala-1992/plakat.svg?styl=jednoduchy', loggedIn: true)->getBody(),
		);
		self::assertStringContainsString(
			'>Vlastní</text>',
			(string) $this->get('/predci/marie-mala-1992/plakat?styl=jednoduchy&nadpis=Vlastn%C3%AD', loggedIn: true)->getBody(),
		);

		// Every part is ticked by default; a part left out of the form's query is turned off and stays unticked.
		self::assertStringContainsString('<input type="checkbox" name="paticka-datum" value="1" checked>', $body);
		self::assertStringContainsString(
			'<select name="nadpis-zobrazit"><option value="vypnuto">vypnuto</option><option value="vlevo" selected>',
			$body,
		);
		self::assertStringContainsString('<option value="vpravo" selected>vpravo</option></select>', $body, 'the footer on the right');
		$parts = (string) $this->get(
			'/predci/antonin-novak-2024/plakat?casti=1&nadpis-zobrazit=vpravo&paticka-zobrazit=vypnuto',
			loggedIn: true,
		)->getBody();
		self::assertStringContainsString(
			'<select name="nadpis-zobrazit"><option value="vypnuto">vypnuto</option><option value="vlevo">vlevo</option><option value="vpravo" selected>',
			$parts,
		);
		self::assertStringContainsString('<select name="paticka-zobrazit"><option value="vypnuto" selected>', $parts);
		self::assertStringContainsString('<input type="checkbox" name="paticka-datum" value="1">', $parts);
		self::assertStringNotContainsString('Předci osoby Antonín Novák ·', $parts);
		self::assertStringContainsString(
			'plakat.svg?rozvrzeni=maleny-strom&amp;format=a1&amp;orientace=na-sirku&amp;styl=historicky&amp;casti=1&amp;nadpis-zobrazit=vpravo&amp;paticka-zobrazit=vypnuto"',
			$parts,
		);

		// The descendants down to the default person, to choose for the painted tree; the default person has none.
		self::assertStringNotContainsString('name="potomci"', $body);
		$grandfather = (string) $this->get('/predci/josef-novak-1960/plakat?potomci=jan-novak-1990', loggedIn: true)->getBody();
		self::assertStringContainsString(
			'<label class="field" data-live="descendants"><span class="field-label">Potomci</span>',
			$grandfather,
		);
		self::assertStringContainsString('<option value="jan-novak-1990" selected>až po Jan Novák</option>', $grandfather);
		self::assertStringContainsString('<option value="antonin-novak-2024" >až po Antonín Novák</option>', $grandfather);
		self::assertStringContainsString('&amp;potomci=jan-novak-1990', $grandfather, 'the download keeps the choice');
		$fan = (string) $this->get('/predci/josef-novak-1960/plakat?rozvrzeni=vejir', loggedIn: true)->getBody();
		self::assertStringContainsString('<label class="field" data-live="descendants" hidden>', $fan, 'only for the painted tree');

		$tree = (string) $this->get('/predci/antonin-novak-2024/plakat?rozvrzeni=strom-vpravo', loggedIn: true)->getBody();
		self::assertStringContainsString('<option value="strom-vpravo" selected>', $tree);
		self::assertStringContainsString('>Josef Novák', $tree);
		self::assertStringContainsString('stroke-dasharray', $tree, 'places of ancestors not found yet');

		foreach (['/predci/antonin-novak-2024', '/vejir/antonin-novak-2024', '/predci/antonin-novak-2024/tisk'] as $page) {
			self::assertStringContainsString(
				'href="/predci/antonin-novak-2024/plakat"',
				(string) $this->get($page, loggedIn: true)->getBody(),
				$page,
			);
		}
		self::assertSame(404, $this->get('/predci/nikdo/plakat', loggedIn: true)->getStatusCode());
		self::assertSame(302, $this->get('/predci/antonin-novak-2024/plakat')->getStatusCode());
	}

	public function testDownloadsThePosterAsSvg(): void
	{
		$page = (string) $this->get('/predci/antonin-novak-2024/plakat?styl=historicky&format=a2', loggedIn: true)->getBody();
		self::assertStringContainsString('<option value="historicky" selected>', $page);
		self::assertStringContainsString(
			'href="/predci/antonin-novak-2024/plakat.svg?rozvrzeni=maleny-strom&amp;format=a2&amp;orientace=na-sirku&amp;styl=historicky" download',
			$page,
			'the same poster, with the readable number of generations',
		);

		$response = $this->get(
			'/predci/antonin-novak-2024/plakat.svg?rozvrzeni=strom&format=a2&generace=1&styl=historicky',
			loggedIn: true,
		);
		$body = (string) $response->getBody();
		self::assertSame(200, $response->getStatusCode(), $body);
		self::assertSame('image/svg+xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
		self::assertSame('attachment; filename="plakat-antonin-novak-2024.svg"', $response->getHeaderLine('Content-Disposition'));
		self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg ', $body);
		self::assertNotFalse(simplexml_load_string($body));
		self::assertStringContainsString('width="594mm" height="420mm"', $body);
		self::assertStringContainsString('>Jan Novák', $body);
		self::assertStringNotContainsString('Josef Novák', $body, 'one generation');
		self::assertStringNotContainsString('/assets/vendor/', $body, 'fonts inside, if built, never linked');

		self::assertSame(404, $this->get('/predci/nikdo/plakat.svg', loggedIn: true)->getStatusCode());
		self::assertSame(302, $this->get('/predci/antonin-novak-2024/plakat.svg')->getStatusCode());
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

	public function testHomeShowsTheStoriesNextToTheMapOfThePlaces(): void
	{
		$home = (string) $this->get('/', loggedIn: true)->getBody();

		self::assertStringContainsString('<section class="section home-feature">', $home);
		self::assertStringContainsString('<h2>Příběh Nováků</h2>', $home);
		self::assertStringContainsString('Od Josefa k Antonínovi', $home);
		self::assertStringContainsString('Kde žili naši předci', $home);
		self::assertStringContainsString('data-link="/mapa"', $home);
		self::assertStringContainsString('&quot;name&quot;:&quot;Praha&quot;', $home);
		self::assertStringContainsString(
			'&quot;colors&quot;:[&quot;#336699&quot;,&quot;#9a8f7d&quot;]',
			$home,
			'the line and the nearest family',
		);
		self::assertStringContainsString('1 místo', $home);
		self::assertStringContainsString('/assets/vendor/leaflet/leaflet.js', $home);
		self::assertStringNotContainsString('story-teaser-photo', $home);
	}

	public function testHomeIntroWithPhoto(): void
	{
		$data = Fixture::siteData();
		$config = $data['config'];
		self::assertIsArray($config);
		$config['uvod'] = ['nadpis' => 'Kováři od brány', 'odstavce' => ['První odstavec.', 'Druhý odstavec.'], 'foto' => 'rodina', 'foto_popis' => 'Rodina před kovárnou'];
		$data['config'] = $config;
		file_put_contents($this->dir . '/site.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
		Images::jpeg($this->dir . '/img/rodina.jpg', 1200, 800);

		$home = (string) $this->get('/', loggedIn: true)->getBody();

		self::assertStringContainsString('<h2>Kováři od brány</h2>', $home);
		self::assertStringContainsString('<p>První odstavec.</p><p>Druhý odstavec.</p>', $home);
		self::assertStringNotContainsString('Od Josefa k Antonínovi', $home, 'the intro replaces the lead');
		self::assertStringContainsString('src="/obrazek/nahled/rodina.jpg"', $home);
		self::assertStringContainsString('<figcaption>Rodina před kovárnou</figcaption>', $home);
		self::assertStringContainsString('Přečíst příběh Nováků →', $home);

		unlink($this->dir . '/img/rodina.jpg');
		self::assertStringNotContainsString(
			'story-teaser-photo',
			(string) $this->get('/', loggedIn: true)->getBody(),
			'a missing photo is left out',
		);
	}

	public function testHomeMapPlaceWithoutLinesHasAColor(): void
	{
		$data = Fixture::siteData();
		$places = $data['places'];
		self::assertIsArray($places);
		self::assertIsArray($places[0]);
		$places[0]['lines'] = [];
		$data['places'] = $places;
		file_put_contents($this->dir . '/site.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

		$home = (string) $this->get('/', loggedIn: true)->getBody();

		self::assertStringContainsString('&quot;colors&quot;:[&quot;#9a8f7d&quot;]', $home);
	}

	public function testHomeWithoutPlacesHasNoMap(): void
	{
		$data = Fixture::siteData();
		$data['places'] = [];
		file_put_contents($this->dir . '/site.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

		$home = (string) $this->get('/', loggedIn: true)->getBody();

		self::assertStringContainsString('home-feature home-feature-single', $home);
		self::assertStringNotContainsString('Kde žili naši předci', $home);
		self::assertStringNotContainsString('leaflet.js', $home);
	}

	public function testMainStoryMovedToTheAddressOfItsLine(): void
	{
		$response = $this->get('/pribeh', loggedIn: true);

		self::assertSame(301, $response->getStatusCode());
		self::assertSame('/pribeh/novakovi', $response->getHeaderLine('Location'));
	}

	public function testStoriesThatDoNotExist(): void
	{
		self::assertSame(404, $this->get('/pribeh/nikdo', loggedIn: true)->getStatusCode());
		self::assertSame(404, $this->get('/pribeh/..%2Fsite', loggedIn: true)->getStatusCode());
	}

	public function testWithoutStoriesThereIsNoStoryPageNorMenuItem(): void
	{
		unlink($this->dir . '/md/pribeh.md');
		unlink($this->dir . '/md/pribeh-malovi.md');

		self::assertSame(404, $this->get('/pribeh', loggedIn: true)->getStatusCode());
		self::assertSame(404, $this->get('/pribeh/novakovi', loggedIn: true)->getStatusCode());
		self::assertSame(404, $this->get('/pribehy', loggedIn: true)->getStatusCode());
		$home = (string) $this->get('/', loggedIn: true)->getBody();
		self::assertStringNotContainsString('href="/pribeh', $home);
	}

	public function testOnlyALineStory(): void
	{
		unlink($this->dir . '/md/pribeh.md');

		self::assertSame(404, $this->get('/pribeh', loggedIn: true)->getStatusCode());
		$home = (string) $this->get('/', loggedIn: true)->getBody();
		self::assertStringContainsString('<a href="/pribeh/malovi" class="">Příběh rodu</a>', $home);
		self::assertStringContainsString('<h2 class="home-subtitle">Příběhy rodů</h2>', $home);
	}

	public function testSearchIndex(): void
	{
		$response = $this->get('/data/hledani.json', loggedIn: true);
		$items = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

		self::assertIsArray($items);
		self::assertCount(4 + 1 + 2 + 1, $items);
		$story = array_values(array_filter($items, static fn (mixed $item): bool => is_array($item) && $item['t'] === 'příběh'))[1] ?? null;
		self::assertSame(
			['t' => 'příběh', 'n' => 'Příběh Malých', 'd' => 'Marie a její předci.', 'x' => 'Marie (1992) Marie Malá se narodila jako dcera mlynáře.', 'u' => '/pribeh/malovi', 'c' => '#993366'],
			$story,
		);
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
		self::assertSame(404, $this->get('/rod/nikdo', loggedIn: true)->getStatusCode());
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
