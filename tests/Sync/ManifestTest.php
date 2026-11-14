<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Sync;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Sync\InvalidManifestException;
use Rodokmen\Site\Sync\Manifest;
use const JSON_THROW_ON_ERROR;

final class ManifestTest extends TestCase
{
	public function testParsesAndSerializes(): void
	{
		$json = self::json(['site.json' => 'data', 'img/krest_jan.jpg' => 'crop', 'md/pribeh.md' => 'story']);
		$manifest = Manifest::fromJson($json);

		self::assertSame(1, $manifest->format);
		self::assertSame('2026-11-13T12:00:00+00:00', $manifest->generated);
		self::assertSame(hash('sha256', 'crop'), $manifest->files['img/krest_jan.jpg']->sha256);
		self::assertSame(4, $manifest->files['img/krest_jan.jpg']->size);
		self::assertEquals($manifest, Manifest::fromJson($manifest->toJson()));
		self::assertSame($manifest->id(), Manifest::fromJson($manifest->toJson())->id());
	}

	public function testIdDependsOnContent(): void
	{
		$a = Manifest::fromJson(self::json(['site.json' => 'a']));
		$b = Manifest::fromJson(self::json(['site.json' => 'b']));

		self::assertNotSame($a->id(), $b->id());
	}

	/** @return iterable<string, array{string}> */
	public static function invalid(): iterable
	{
		yield 'not json' => ['{'];
		yield 'no files' => ['{"format": 1}'];
		yield 'other format' => [self::json(['site.json' => 'x'], 2)];
		yield 'without site.json' => [self::json(['img/a.jpg' => 'x'])];
		yield 'path traversal' => [self::json(['site.json' => 'x', '../index.php' => 'x'])];
		yield 'nested path' => [self::json(['site.json' => 'x', 'img/a/b.jpg' => 'x'])];
		yield 'php file' => [self::json(['site.json' => 'x', 'img/a.php' => 'x'])];
		yield 'bad hash' => ['{"format": 1, "files": {"site.json": {"sha256": "abc", "size": 1}}}'];
		yield 'negative size' => ['{"format": 1, "files": {"site.json": {"sha256": "' . hash('sha256', '') . '", "size": -1}}}'];
	}

	#[DataProvider('invalid')]
	public function testRejectsInvalidManifest(string $json): void
	{
		$this->expectException(InvalidManifestException::class);
		Manifest::fromJson($json);
	}

	/** @param array<string, string> $contents path => file content */
	public static function json(array $contents, int $format = 1): string
	{
		$files = [];
		foreach ($contents as $path => $content) {
			$files[$path] = ['sha256' => hash('sha256', $content), 'size' => strlen($content)];
		}

		return json_encode(['format' => $format, 'generated' => '2026-11-13T12:00:00+00:00', 'files' => $files], JSON_THROW_ON_ERROR);
	}
}
