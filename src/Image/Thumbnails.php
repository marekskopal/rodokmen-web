<?php

declare(strict_types=1);

namespace Rodokmen\Site\Image;

use Rodokmen\Site\Config;
use const IMAGETYPE_JPEG;

/**
 * Smaller WebP copies of the crops and photos in img/ for `srcset`, made on the first request and kept in
 * var/cache/thumbs. The bundle holds only JPEGs and the sync client has no image library, so they are made here.
 *
 * A thumbnail is named after its source and the source's modification time and size
 * (thumbs/{width}/{name}-{mtime}-{size}.webp): a changed source gets a new name, so a stale copy is never served,
 * and prune() can tell the stale ones from the name alone.
 */
final readonly class Thumbnails
{
	/** Widths the website asks for; only these are made, so nobody can fill the cache with arbitrary sizes. */
	public const Widths = [480, 960, 1440];

	/** Unfinished thumbnails of a crashed request older than this are removed by prune(). */
	private const StaleTemp = 600;

	private const Name = '/^([a-z0-9_\-]+)-(\d+)-(\d+)\.webp$/';

	/** @param bool|null $webp whether GD can read JPEG and write WebP (null: ask GD) */
	public function __construct(private Config $config, private WebpResizer $resizer, private ?bool $webp = null)
	{
	}

	public function isSupported(): bool
	{
		return $this->webp ?? (
			function_exists('imagewebp')
			&& function_exists('imagecreatefromjpeg')
			&& (imagetypes() & IMG_WEBP) !== 0
			&& (imagetypes() & IMG_JPG) !== 0
		);
	}

	/**
	 * Size of the source img/{name}.jpg when WebP copies of it can be made (GD support, a source small enough to
	 * decode), otherwise null.
	 */
	public function resizableSize(string $name): ?ImageSize
	{
		$size = $this->isSupported() ? $this->sourceSize($name) : null;

		return $size !== null && $this->resizer->canResize($size) ? $size : null;
	}

	/** Width and height of the source img/{name}.jpg (read from its header), or null when it is missing. */
	public function sourceSize(string $name): ?ImageSize
	{
		$info = @getimagesize($this->source($name));
		if ($info === false || $info[2] !== IMAGETYPE_JPEG || $info[0] < 1 || $info[1] < 1) {
			return null;
		}

		return new ImageSize($info[0], $info[1]);
	}

	/**
	 * Path of the thumbnail of img/{name}.jpg at most $width wide (never wider than the source), made when it is
	 * missing; null when it cannot be made (no WebP support, no or broken source, too large to decode).
	 */
	public function thumbnail(string $name, int $width): ?string
	{
		if ($width < 1 || !in_array($width, self::Widths, true) || !$this->isSupported()) {
			return null;
		}

		$source = $this->source($name);
		$modified = @filemtime($source);
		$size = @filesize($source);
		if ($modified === false || $size === false) {
			return null;
		}

		$path = $this->dir($width) . '/' . $name . '-' . $modified . '-' . $size . '.webp';
		if (is_file($path)) {
			return $path;
		}

		return $this->make($name, $width, $path) ? $path : null;
	}

	/** Removes thumbnails whose source is gone or has changed, and leftovers of crashed requests; returns how many. */
	public function prune(): int
	{
		$removed = 0;
		foreach (self::Widths as $width) {
			$dir = $this->dir($width);
			$names = is_dir($dir) ? scandir($dir) : false;
			foreach ($names === false ? [] : $names as $file) {
				if ($file !== '.' && $file !== '..' && $this->isStale($file, $dir . '/' . $file) && @unlink($dir . '/' . $file)) {
					$removed++;
				}
			}
		}

		return $removed;
	}

	/** @param positive-int $width */
	private function make(string $name, int $width, string $path): bool
	{
		$size = $this->sourceSize($name);
		if ($size === null || !$this->resizer->resize($this->source($name), $size, $width, $path)) {
			return false;
		}

		$this->removeOlderCopies($name, $path);

		return true;
	}

	/**
	 * A new thumbnail means a new or changed source: its older copies go at once, also when the data are uploaded
	 * with rsync (prune() runs only after an upload through the API).
	 */
	private function removeOlderCopies(string $name, string $path): void
	{
		$copies = glob(dirname($path) . '/' . $name . '-*.webp');
		foreach ($copies === false ? [] : $copies as $copy) {
			if ($copy !== $path && preg_match(self::Name, basename($copy), $m) === 1 && $m[1] === $name) {
				@unlink($copy);
			}
		}
	}

	private function isStale(string $file, string $path): bool
	{
		if (str_ends_with($file, '.tmp')) {
			$modified = @filemtime($path);

			return $modified !== false && $modified < time() - self::StaleTemp;
		}

		if (preg_match(self::Name, $file, $m) !== 1) {
			return false;
		}

		$source = $this->source($m[1]);

		return @filemtime($source) !== (int) $m[2] || @filesize($source) !== (int) $m[3];
	}

	private function source(string $name): string
	{
		return $this->config->dataDir . '/img/' . $name . '.jpg';
	}

	private function dir(int $width): string
	{
		return $this->config->cacheDir . '/thumbs/' . $width;
	}
}
