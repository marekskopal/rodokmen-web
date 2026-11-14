<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Demo;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Record;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Kernel;
use Rodokmen\Site\Tests\Support\DemoFamily;
use Rodokmen\Site\Tests\Support\DemoRecords;
use Rodokmen\Site\Tests\Support\Fixture;
use const IMAGETYPE_JPEG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;
use const PHP_URL_QUERY;

/**
 * The demo family (README screenshots, `make demo`) has to stay valid when the export format changes.
 */
final class DemoFamilyTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir(DemoFamily::siteData());
		foreach (DemoFamily::notes() as $name => $text) {
			file_put_contents($this->dir . '/md/' . $name . '.md', $text);
		}
		putenv('SITE_DATA_DIR=' . $this->dir);
		putenv('SITE_CACHE_DIR=' . $this->dir . '/cache');
	}

	protected function tearDown(): void
	{
		putenv('SITE_DATA_DIR');
		putenv('SITE_CACHE_DIR');
		Fixture::removeDir($this->dir);
	}

	public function testLoads(): void
	{
		$data = new SiteDataLoader(Fixture::config($this->dir))->load();

		self::assertSame('marek-skopal', $data->root);
		self::assertSame('Marek Skopal', $data->people['marek-skopal']->name);
		self::assertSame('', $data->people['marek-skopal']->y, 'no facts about the real person');
		self::assertSame([], $data->people['marek-skopal']->facts, 'no facts about the real person');
		foreach ($data->places as $place) {
			foreach ($place->lines as $line) {
				self::assertNotContains('marek-skopal', $line->people, 'the real person is not on the map');
			}
		}
		self::assertCount(8, $data->lines);
		self::assertGreaterThan(20, count($data->records));
		self::assertSame(
			json_encode(DemoFamily::siteData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
			json_encode(DemoFamily::siteData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
			'deterministic',
		);
	}

	public function testScansHaveImages(): void
	{
		$data = new SiteDataLoader(Fixture::config($this->dir))->load();
		$scanned = array_keys(array_filter($data->records, static fn (Record $record): bool => $record->img));

		self::assertSame(DemoRecords::Scans, $scanned);
		foreach ($scanned as $key) {
			self::assertTrue($data->records[$key]->full);
			foreach (['img', 'full'] as $folder) {
				$size = getimagesize(DemoRecords::ImageDir . '/' . $folder . '/' . $key . '.jpg');
				self::assertNotFalse($size, $folder . '/' . $key . '.jpg');
				self::assertSame(IMAGETYPE_JPEG, $size[2]);
			}
		}
	}

	/** @return iterable<string, array{string}> */
	public static function pages(): iterable
	{
		foreach (['/', '/predci', '/strom', '/vejir', '/generace', '/mapa', '/zapisy', '/osoba/antonin-skopal-1889', '/zapis/unicov_krest_antonin_skopal_1889', '/zapis/litovel_krest_josef_skopal_1921', '/zajimavosti', '/pribeh', '/denik', '/hledame', '/hledat?q=Skopal'] as $uri) {
			yield $uri => [$uri];
		}
	}

	#[DataProvider('pages')]
	public function testPageRenders(string $uri): void
	{
		$kernel = Kernel::create(dirname(__DIR__, 2));
		parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
		$request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://localhost' . $uri, 'GET')->withQueryParams($query);

		$response = $kernel->handle(Fixture::logIn($request, Fixture::config($this->dir)));

		self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
	}
}
