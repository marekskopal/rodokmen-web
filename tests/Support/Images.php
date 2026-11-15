<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Support;

use RuntimeException;

/** Test images made with GD. */
final class Images
{
	/**
	 * Writes a JPEG of $width × $height to $path (creating its folder).
	 *
	 * @param positive-int $width
	 * @param positive-int $height
	 */
	public static function jpeg(string $path, int $width, int $height): void
	{
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}

		$image = imagecreatetruecolor($width, $height);
		$colour = $image === false ? false : imagecolorallocate($image, 200, 180, 140);
		if ($image === false || $colour === false) {
			throw new RuntimeException('Cannot make the test image ' . $path);
		}

		imagefill($image, 0, 0, $colour);
		if (!imagejpeg($image, $path, 85)) {
			throw new RuntimeException('Cannot write the test image ' . $path);
		}
	}
}
