<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Auth;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Auth\ThrottleCleanup;
use Rodokmen\Site\Auth\ThrottleScope;
use Rodokmen\Site\Tests\Support\Fixture;
use function dirname;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function glob;
use function json_decode;
use function json_encode;
use function microtime;
use function mkdir;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function time;
use function touch;
use function var_export;
use const LOCK_EX;
use const PHP_BINARY;

final class ThrottleCleanupTest extends TestCase
{
	private const Window = 900;

	private string $dir;

	private string $throttle;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		$this->throttle = $this->dir . '/cache/throttle';
		mkdir($this->throttle, 0777, true);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testRemovesOnlyFilesWithoutRecentAttempts(): void
	{
		$this->write('login-expired.json', [time() - 1000, time() - 2000]);
		$this->write('login-empty.json', []);
		// the format before throttle scopes
		$this->write('0123abcd.json', [time() - 5000]);
		file_put_contents($this->throttle . '/broken.json', 'not json');
		touch($this->throttle . '/broken.json', time() - 1000);
		// a login succeeded a moment ago: removed later
		$this->write('login-just-emptied.json', [], age: 5);
		$this->write('login-current.json', [time() - 1000, time() - 10], age: 10);
		$this->write('login-all.json', [time() - 5], age: 5);
		file_put_contents($this->throttle . '/notes.txt', '[]');

		self::assertSame(4, $this->cleanup()->run(self::Window, 10.0));
		self::assertSame(
			['login-all.json', 'login-current.json', 'login-just-emptied.json', 'notes.txt'],
			$this->names(),
		);
	}

	public function testFileInUseIsLeftAlone(): void
	{
		$this->write('login-busy.json', []);
		$handle = fopen($this->throttle . '/login-busy.json', 'r');
		self::assertNotFalse($handle);
		flock($handle, LOCK_EX);

		try {
			self::assertSame(0, $this->cleanup()->run(self::Window, 10.0));
		} finally {
			fclose($handle);
		}

		self::assertSame(1, $this->cleanup()->run(self::Window, 10.0));
	}

	public function testRunsAtMostOncePerMinute(): void
	{
		$this->write('login-a.json', []);
		$this->cleanup()->maybeRun(self::Window);
		self::assertSame([], $this->names());

		$this->write('login-b.json', []);
		$this->cleanup()->maybeRun(self::Window);
		self::assertSame(['login-b.json'], $this->names(), 'too soon');

		touch($this->throttle . '/.cleanup', time() - 120);
		$this->cleanup()->maybeRun(self::Window);
		self::assertSame([], $this->names());
	}

	public function testGoesOnWhereTheLastRunStopped(): void
	{
		for ($i = 0; $i < 30; $i++) {
			$this->write(sprintf('login-%02d.json', $i), []);
		}

		// A budget of nothing checks one file per run; the runs still get through all of them.
		$removed = 0;
		for ($run = 0; $run < 100 && $this->names() !== []; $run++) {
			$removed += $this->cleanup()->run(self::Window, 0.0);
		}

		self::assertSame(30, $removed);
		self::assertSame([], $this->names());
	}

	public function testThrottleCleansUpWhileCounting(): void
	{
		$this->write('login-old-client.json', [time() - 1000]);
		$throttle = Fixture::throttle(Fixture::config($this->dir));

		$throttle->registerFailure(new ServerRequest(['REMOTE_ADDR' => '203.0.113.5']), ThrottleScope::Login);

		self::assertNotContains('login-old-client.json', $this->names());
		self::assertCount(2, $this->names(), 'the new client and all clients');
	}

	public function testAttemptsAreNotLostWhenTheCleanupRemovesTheFileMeanwhile(): void
	{
		// An expired file is removed by cleanups while new failures are counted into it at the same moment: every
		// failure has to end up in the file on disk, none in a removed one.
		for ($round = 0; $round < 3; $round++) {
			$this->write('sync-race.json', [time() - 1000]);
			clearstatcache();
			$start = microtime(true) + 0.5;
			$processes = [];
			for ($i = 0; $i < 12; $i++) {
				$code = $i % 2 === 0
					? '$f = Rodokmen\Site\Auth\ThrottleFile::open(%2$s, 900); $f?->write([...$f->read(), time()]); $f?->close();'
					: 'new Rodokmen\Site\Auth\ThrottleCleanup($config)->run(900, 1.0);';
				$script = sprintf(
					'require %1$s; $config = Rodokmen\Site\Tests\Support\Fixture::config(%3$s); if (%4$F > microtime(true)) { time_sleep_until(%4$F); } ' . $code,
					var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
					var_export($this->throttle . '/sync-race.json', true),
					var_export($this->dir, true),
					$start,
				);
				$process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
				self::assertIsResource($process);
				$processes[] = [$process, $pipes];
			}

			foreach ($processes as [$process, $streams]) {
				self::assertSame('', stream_get_contents($streams[1]) . stream_get_contents($streams[2]));
				fclose($streams[1]);
				fclose($streams[2]);
				proc_close($process);
			}

			$attempts = json_decode((string) @file_get_contents($this->throttle . '/sync-race.json'), true);
			self::assertIsArray($attempts, 'round ' . $round);
			self::assertCount(6, $attempts, 'round ' . $round);
		}
	}

	/**
	 * @param list<int> $attempts
	 * @param int $age seconds since the file was last written
	 */
	private function write(string $name, array $attempts, int $age = 1000): void
	{
		file_put_contents($this->throttle . '/' . $name, json_encode($attempts));
		touch($this->throttle . '/' . $name, time() - $age);
	}

	/** @return list<string> */
	private function names(): array
	{
		$files = glob($this->throttle . '/*');
		self::assertIsArray($files);

		return array_map('basename', $files);
	}

	private function cleanup(): ThrottleCleanup
	{
		return new ThrottleCleanup(Fixture::config($this->dir));
	}
}
