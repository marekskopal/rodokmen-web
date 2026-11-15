<?php

declare(strict_types=1);

namespace Rodokmen\Site\Image;

/** Width and height of an image in pixels. */
final readonly class ImageSize
{
	/**
	 * @param positive-int $width
	 * @param positive-int $height
	 */
	public function __construct(public int $width, public int $height)
	{
	}
}
