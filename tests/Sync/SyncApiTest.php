<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Sync;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Rodokmen\Site\Kernel;
use Rodokmen\Site\Tests\Support\Fixture;
use const JSON_THROW_ON_ERROR;

/**
 * The upload API through the whole application: token, plan, upload, commit.
 */
final class SyncApiTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		putenv('SITE_DATA_DIR=' . $this->dir . '/site/data');
		putenv('SITE_CACHE_DIR=' . $this->dir . '/cache');
		putenv('SITE_SYNC_TOKEN=sync-token');
	}

	protected function tearDown(): void
	{
		putenv('SITE_DATA_DIR');
		putenv('SITE_CACHE_DIR');
		putenv('SITE_SYNC_TOKEN=');
		Fixture::removeDir($this->dir);
	}

	public function testDisabledWithoutToken(): void
	{
		putenv('SITE_SYNC_TOKEN=');

		self::assertSame(404, $this->send('POST', '/api/sync/plan', '{}', 'sync-token')->getStatusCode());
	}

	public function testRejectsWrongToken(): void
	{
		$response = $this->send('POST', '/api/sync/plan', '{}', 'wrong');

		self::assertSame(401, $response->getStatusCode());
		self::assertSame(['error' => 'Invalid token'], $this->json($response));
	}

	public function testThrottlesRepeatedWrongTokens(): void
	{
		for ($i = 0; $i < 5; $i++) {
			$this->send('POST', '/api/sync/plan', '{}', 'wrong');
		}

		self::assertSame(429, $this->send('POST', '/api/sync/plan', '{}', 'sync-token')->getStatusCode());
	}

	public function testAcceptsTokenHeaderWhenAuthorizationIsDropped(): void
	{
		$request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://localhost/api/sync/plan', 'POST')
			->withBody((new StreamFactory())->createStream(ManifestTest::json(['site.json' => 'x'])))
			->withHeader('X-Sync-Token', 'sync-token');

		self::assertSame(200, Kernel::create(dirname(__DIR__, 2))->handle($request)->getStatusCode());
	}

	public function testRejectsWrongTokenHeader(): void
	{
		$request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://localhost/api/sync/plan', 'POST')
			->withHeader('X-Sync-Token', 'wrong');

		self::assertSame(401, Kernel::create(dirname(__DIR__, 2))->handle($request)->getStatusCode());
	}

	public function testRejectsInvalidManifest(): void
	{
		$response = $this->send('POST', '/api/sync/plan', '{"format": 1, "files": {"../x.php": {}}}', 'sync-token');

		self::assertSame(400, $response->getStatusCode());
	}

	public function testPlanUploadCommit(): void
	{
		$siteJson = (string) file_get_contents(dirname($this->dir . '/x') . '/site.json');
		$manifest = ManifestTest::json(['site.json' => $siteJson, 'img/krest.jpg' => 'crop']);

		$plan = $this->json($this->send('POST', '/api/sync/plan', $manifest, 'sync-token'));
		self::assertSame(['files' => 2, 'missing' => [hash('sha256', $siteJson), hash('sha256', 'crop')], 'current' => false], $plan);

		self::assertSame(409, $this->send('POST', '/api/sync/commit', $manifest, 'sync-token')->getStatusCode());

		foreach ([$siteJson, 'crop'] as $content) {
			$upload = $this->send('PUT', '/api/sync/file/' . hash('sha256', $content), $content, 'sync-token');
			self::assertSame(201, $upload->getStatusCode());
		}
		self::assertSame(422, $this->send('PUT', '/api/sync/file/' . hash('sha256', 'x'), 'y', 'sync-token')->getStatusCode());

		$commit = $this->send('POST', '/api/sync/commit', $manifest, 'sync-token');
		self::assertSame(200, $commit->getStatusCode());
		self::assertSame('crop', file_get_contents($this->dir . '/site/data/img/krest.jpg'));

		$again = $this->json($this->send('POST', '/api/sync/plan', $manifest, 'sync-token'));
		self::assertSame(['files' => 2, 'missing' => [], 'current' => true], $again);

		// The new data are served right away.
		$home = $this->send('GET', '/prihlaseni', '', '');
		self::assertSame(200, $home->getStatusCode());
		self::assertStringContainsString('Rodokmen Testových', (string) $home->getBody());
	}

	public function testAnUnexpectedFaultIsReportedToTheClient(): void
	{
		putenv('SITE_LOG_DIR=' . $this->dir . '/log');
		putenv('SITE_LOG_STDERR=0');
		// The uploads cannot be stored: their folder's name is taken by a file.
		mkdir($this->dir . '/site');
		file_put_contents($this->dir . '/site/sync-uploads', '');

		try {
			$response = $this->send('PUT', '/api/sync/file/' . hash('sha256', 'crop'), 'crop', 'sync-token');
		} finally {
			putenv('SITE_LOG_DIR');
			putenv('SITE_LOG_STDERR');
		}

		self::assertSame(500, $response->getStatusCode());
		$error = $this->json($response)['error'];
		self::assertIsString($error);
		self::assertStringStartsWith('Server error: Cannot create ' . $this->dir . '/site/sync-uploads: mkdir(): ', $error);
		self::assertStringContainsString(substr($error, 14), (string) file_get_contents($this->dir . '/log/error.log'));
	}

	public function testRollback(): void
	{
		$rollback = $this->send('POST', '/api/sync/rollback', '', 'sync-token');
		self::assertSame(409, $rollback->getStatusCode());
		self::assertSame(
			'There is no release to roll back from (the data were not uploaded by the sync API)',
			$this->json($rollback)['error'],
		);
		self::assertSame(401, $this->send('POST', '/api/sync/rollback', '', 'wrong')->getStatusCode());

		$siteJson = (string) file_get_contents($this->dir . '/site.json');
		foreach ([$siteJson, 'crop'] as $content) {
			$this->send('PUT', '/api/sync/file/' . hash('sha256', $content), $content, 'sync-token');
		}
		$first = $this->json($this->send('POST', '/api/sync/commit', ManifestTest::json(['site.json' => $siteJson]), 'sync-token'));
		$manifest = ManifestTest::json(['site.json' => $siteJson, 'img/krest.jpg' => 'crop']);
		self::assertSame(200, $this->send('POST', '/api/sync/commit', $manifest, 'sync-token')->getStatusCode());

		$thumbnail = $this->dir . '/cache/thumbs/480/krest-1-4.webp';
		mkdir(dirname($thumbnail), 0777, true);
		file_put_contents($thumbnail, 'old');

		$rollback = $this->send('POST', '/api/sync/rollback', '', 'sync-token');
		self::assertSame(200, $rollback->getStatusCode());
		self::assertFileDoesNotExist($thumbnail, 'thumbnails of removed images are pruned');
		self::assertSame(['release' => $first['release']], $this->json($rollback));
		self::assertFileDoesNotExist($this->dir . '/site/data/img/krest.jpg');
		self::assertFalse($this->json($this->send('POST', '/api/sync/plan', $manifest, 'sync-token'))['current']);
	}

	private function send(string $method, string $uri, string $body, string $token): ResponseInterface
	{
		$request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1'], [], 'http://localhost' . $uri, $method)
			->withBody((new StreamFactory())->createStream($body));
		if ($token !== '') {
			$request = $request->withHeader('Authorization', 'Bearer ' . $token);
		}

		return Kernel::create(dirname(__DIR__, 2))->handle($request);
	}

	/** @return array<mixed> */
	private function json(ResponseInterface $response): array
	{
		$data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($data);

		return $data;
	}
}
