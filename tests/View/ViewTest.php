<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Image\Thumbnails;
use Rodokmen\Site\Image\WebpResizer;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\Tests\Support\Images;
use Rodokmen\Site\View\View;
use Stringable;

final class ViewTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private View $view;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
		$this->view = new View($config, $this->repository, new Thumbnails($config, new WebpResizer()));
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testEscape(): void
	{
		self::assertSame('&lt;a href=&quot;x&quot;&gt;&#039;', $this->view->e('<a href="x">\''));
		self::assertSame('5', $this->view->e(5));
		self::assertSame('', $this->view->e(null));
		self::assertSame('', $this->view->e(['array']));
		self::assertSame('ok', $this->view->e(new class implements Stringable {
			public function __toString(): string
			{
				return 'ok';
			}
		}));
	}

	public function testSrcsetListsTheWebpCopiesAtTheirRealWidths(): void
	{
		Images::jpeg($this->dir . '/img/krest.jpg', 1000, 500);
		$config = Fixture::config($this->dir);
		$view = new View($config, $this->repository, new Thumbnails($config, new WebpResizer(), true));

		self::assertSame(
			' srcset="/obrazek/nahled/480/krest.webp 480w, /obrazek/nahled/960/krest.webp 960w, /obrazek/nahled/1440/krest.webp 1000w"'
			. ' sizes="(max-width: 860px) 100vw, 800px"',
			$view->srcset('krest', '(max-width: 860px) 100vw, 800px'),
		);

		Images::jpeg($this->dir . '/img/maly.jpg', 300, 200);
		self::assertSame(
			' srcset="/obrazek/nahled/480/maly.webp 300w" sizes="240px"',
			$view->srcset('maly', '240px'),
			'a small source is listed once, at its own width',
		);
		self::assertSame('', $view->srcset('chybi', '240px'), 'no source');

		$withoutWebp = new View($config, $this->repository, new Thumbnails($config, new WebpResizer(), false));
		self::assertSame('', $withoutWebp->srcset('krest', '240px'));
	}

	public function testUrls(): void
	{
		self::assertSame('/osoba/jan%20nov%C3%A1k', $this->view->personUrl('jan novák'));
		self::assertSame('/predci/jan', $this->view->explorerUrl('jan'));
		self::assertSame('/obrazek/nahled/a.jpg', $this->view->imageUrl('a'));
		self::assertSame('/obrazek/plny/a.jpg', $this->view->imageUrl('a', true));
	}

	public function testPersonLink(): void
	{
		$link = $this->view->personLink('jan-novak-1990');

		self::assertStringContainsString('href="/osoba/jan-novak-1990"', $link);
		self::assertStringContainsString('Jan Novák', $link);
		self::assertStringContainsString('--c:#336699', $link);
		self::assertSame('<span class="muted">neuveden</span>', $this->view->personLink(null, 'neuveden'));
		self::assertSame('<span class="muted">neznámý</span>', $this->view->personLink('nikdo'));
	}

	public function testLineDot(): void
	{
		self::assertSame('', $this->view->lineDot(null));
		self::assertStringContainsString('--c:#336699', $this->view->lineDot(8));
	}

	public function testStatusAndYears(): void
	{
		$jan = $this->repository->person('jan-novak-1990');
		self::assertNotNull($jan);

		self::assertStringContainsString('badge-doc', $this->view->status($jan));
		self::assertStringContainsString('doloženo', $this->view->status($jan));
		self::assertSame('1990 – ?', $this->view->years($jan));
	}

	public function testStatusMark(): void
	{
		self::assertStringContainsString('stm-doc', $this->view->statusMark('doc'));
		self::assertStringContainsString('doloženo matrikou', $this->view->statusMark('doc'));
		self::assertStringContainsString('stm-prob', $this->view->statusMark('prob'));
		self::assertStringContainsString('údaj od rodiny', $this->view->statusMark('fam'));
		self::assertStringContainsString('stm-mh', $this->view->statusMark('mh'));
		self::assertSame('', $this->view->statusMark('unk'));
		self::assertSame('', $this->view->statusMark('<script>'));
	}

	/** @return iterable<string, array{string, string, string, string}> */
	public static function facts(): iterable
	{
		yield 'birth' => ['* 1. 5. 1990 Praha', 'birth', 'Narození', '1. 5. 1990 Praha'];
		yield 'marriage' => ['× 2015 Praha', 'marriage', 'Sňatek', '2015 Praha'];
		yield 'marriage symbol' => ['⚭ 2015', 'marriage', 'Sňatek', '2015'];
		yield 'death' => ['† 2020', 'death', 'Úmrtí', '2020'];
		yield 'baptism' => ['pokřtěn 2. 5. 1990', 'birth', 'Křest', 'pokřtěn 2. 5. 1990'];
		yield 'other' => ['  kovář v Lukavici  ', 'info', '', 'kovář v Lukavici'];
	}

	#[DataProvider('facts')]
	public function testFact(string $fact, string $type, string $label, string $text): void
	{
		self::assertSame(['type' => $type, 'label' => $label, 'text' => $text], $this->view->fact($fact));
	}

	public function testFactYear(): void
	{
		self::assertSame(1746, $this->view->factYear('* 23. 4. 1746 Lomnice'));
		self::assertSame(2012, $this->view->factYear('† 2012'));
		self::assertNull($this->view->factYear('čp. 1234 a 12345'));
	}

	public function testKindLabel(): void
	{
		self::assertSame('Křest', $this->view->kindLabel('N'));
		self::assertSame('Úmrtí', $this->view->kindLabel('Z'));
		self::assertSame('X', $this->view->kindLabel('X'));
	}

	public function testPlural(): void
	{
		self::assertSame('1 osoba', $this->view->plural(1, 'osoba', 'osoby', 'osob'));
		self::assertSame('3 osoby', $this->view->plural(3, 'osoba', 'osoby', 'osob'));
		self::assertSame('5 osob', $this->view->plural(5, 'osoba', 'osoby', 'osob'));
		self::assertSame('0 osob', $this->view->plural(0, 'osoba', 'osoby', 'osob'));
	}

	public function testJsonAndMarkdown(): void
	{
		self::assertSame('{&quot;a&quot;:&quot;č/&lt;&quot;}', $this->view->json(['a' => 'č/<']));
		self::assertStringContainsString('<h2>Nadpis</h2>', $this->view->markdown('## Nadpis'));
		self::assertStringNotContainsString('<script>', $this->view->markdown('<script>alert(1)</script>'));
	}

	public function testRenderAndPage(): void
	{
		$html = $this->view->render('search', ['query' => '<b>']);
		self::assertStringContainsString('value="&lt;b&gt;"', $html);

		$response = $this->view->page('error', ['title' => 'Chyba', 'message' => 'Něco'], 500);
		self::assertSame(500, $response->getStatusCode());
		$body = (string) $response->getBody();
		self::assertStringContainsString('Chyba · Rodokmen Testových', $body);
		self::assertStringContainsString('Testovací patička', $body);
	}

	public function testSearchHintDefaultsToGenericExample(): void
	{
		self::assertSame('Např. příjmení, obec, řemeslo…', $this->view->searchHint());

		$config = Fixture::config($this->dir, searchHint: 'Např. Novák, Lomnice');
		self::assertSame(
			'Např. Novák, Lomnice',
			(new View($config, $this->repository, new Thumbnails($config, new WebpResizer())))->searchHint(),
		);
	}
}
