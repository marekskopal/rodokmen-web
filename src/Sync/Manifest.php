<?php

declare(strict_types=1);

namespace Rodokmen\Site\Sync;

use JsonException;
use function array_map;
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Description of a data bundle (manifest.json): the format version and every file with its hash and size.
 * It is the contract between the exporter side (bin/rodokmen-sync) and the website.
 */
final readonly class Manifest
{
	/** Bundle format this website understands. */
	public const Format = 1;

	public const FileName = 'manifest.json';

	/** Files a bundle may contain; nothing else is ever written into the data directory. */
	private const PathPattern = '~^(site\.json|md/[a-z0-9_-]+\.md|(img|full)/[a-z0-9_-]+\.jpg)$~';

	/** @param array<string, ManifestFile> $files by path relative to the data directory */
	public function __construct(public int $format, public string $generated, public array $files)
	{
	}

	public static function fromJson(string $json): self
	{
		try {
			$data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new InvalidManifestException('Manifest is not valid JSON: ' . $e->getMessage(), 0, $e);
		}

		if (!is_array($data) || !is_int($data['format'] ?? null) || !is_array($data['files'] ?? null)) {
			throw new InvalidManifestException('Manifest needs "format" (int) and "files" (object)');
		}
		if ($data['format'] !== self::Format) {
			throw new InvalidManifestException(
				'Unsupported bundle format ' . $data['format'] . ', this website reads format ' . self::Format,
			);
		}

		$files = [];
		foreach ($data['files'] as $path => $file) {
			$files[(string) $path] = self::file((string) $path, $file);
		}
		if (!isset($files['site.json'])) {
			throw new InvalidManifestException('Manifest does not contain site.json');
		}

		$generated = $data['generated'] ?? '';

		return new self(self::Format, is_string($generated) ? $generated : '', $files);
	}

	public static function isAllowedPath(string $path): bool
	{
		return preg_match(self::PathPattern, $path) === 1;
	}

	public function toJson(): string
	{
		$files = array_map(static fn (ManifestFile $f): array => ['sha256' => $f->sha256, 'size' => $f->size], $this->files);
		ksort($files);

		return json_encode(
			['format' => $this->format, 'generated' => $this->generated, 'files' => $files],
			JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
		) . "\n";
	}

	/** Short stable identifier of the bundle content. */
	public function id(): string
	{
		$files = array_map(static fn (ManifestFile $f): string => $f->sha256, $this->files);
		ksort($files);

		return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
	}

	private static function file(string $path, mixed $file): ManifestFile
	{
		if (!self::isAllowedPath($path)) {
			throw new InvalidManifestException('File not allowed in a bundle: ' . $path);
		}
		if (!is_array($file) || !is_string($file['sha256'] ?? null) || !is_int($file['size'] ?? null)) {
			throw new InvalidManifestException('File ' . $path . ' needs "sha256" (string) and "size" (int)');
		}
		if (preg_match('/^[0-9a-f]{64}$/', $file['sha256']) !== 1 || $file['size'] < 0) {
			throw new InvalidManifestException('File ' . $path . ' has an invalid hash or size');
		}

		return new ManifestFile($file['sha256'], $file['size']);
	}
}
