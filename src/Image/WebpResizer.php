<?php

declare(strict_types=1);

namespace Rodokmen\Site\Image;

use GdImage;

/**
 * Scales a JPEG down with GD and writes it as WebP.
 */
final readonly class WebpResizer
{
	/** Larger sources are not decoded: GD needs about 5 bytes per pixel, a 40 MP scan about 200 MB. */
	public const MaxPixels = 40000000;

	/** Bytes GD needs per pixel of a decoded true-colour image (with some headroom for the scaled copy). */
	private const BytesPerPixel = 6;

	private const Quality = 80;

	/**
	 * Writes $source ($size) scaled to $width (never wider than the source) to $target; false when it cannot (too
	 * large, broken, not writable). The file is written next to $target and renamed, so that a parallel request
	 * never reads a half-written file.
	 *
	 * @param positive-int $width
	 */
	public function resize(string $source, ImageSize $size, int $width, string $target): bool
	{
		if (!$this->canResize($size)) {
			return false;
		}

		$image = @imagecreatefromjpeg($source);
		$scaled = $image === false ? null : $this->scale($image, $size, min($width, $size->width));
		if ($scaled === null) {
			return false;
		}

		$dir = dirname($target);
		if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			return false;
		}

		$temp = $dir . '/.' . bin2hex(random_bytes(8)) . '.tmp';
		if (!@imagewebp($scaled, $temp, self::Quality) || !@rename($temp, $target)) {
			@unlink($temp);

			return false;
		}

		return true;
	}

	/**
	 * Whether a source of this size can be decoded: at most MaxPixels and within PHP's memory_limit (running out of
	 * memory is a fatal error that no `@` or catch can stop).
	 */
	public function canResize(ImageSize $size): bool
	{
		$pixels = $size->width * $size->height;
		if ($pixels > self::MaxPixels) {
			return false;
		}

		$limit = ini_parse_quantity(ini_get('memory_limit'));

		return $limit <= 0 || $pixels * self::BytesPerPixel < $limit - memory_get_usage();
	}

	/** @param positive-int $width */
	private function scale(GdImage $image, ImageSize $size, int $width): ?GdImage
	{
		if ($width === $size->width) {
			return $image;
		}

		// imagecopyresampled() rather than imagescale(): some GD builds fail on imagescale's better modes.
		$height = max(1, (int) round($size->height * $width / $size->width));
		$scaled = imagecreatetruecolor($width, $height);
		if ($scaled === false) {
			return null;
		}

		imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, $size->width, $size->height);

		return $scaled;
	}
}
