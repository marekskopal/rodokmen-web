<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

/** What the visitor chose for the poster; parsed from the query string, invalid values fall back to defaults. */
final readonly class PosterOptions
{
	public const MaxTitleLength = 120;

	public function __construct(
		public PosterLayout $layout,
		public PaperFormat $format,
		public Orientation $orientation,
		public int $depth,
		public PosterStyle $style,
		public string $title,
	) {
	}

	/**
	 * @param array<mixed> $query
	 * @param int $available generations above the person that can be drawn; also the default
	 */
	public static function fromQuery(array $query, int $available): self
	{
		$requested = self::string($query, 'generace');
		$depth = ctype_digit($requested) ? (int) $requested : $available;
		$title = trim((string) preg_replace('/\s+/u', ' ', self::string($query, 'nadpis')));

		return new self(
			PosterLayout::tryFrom(self::string($query, 'rozvrzeni')) ?? PosterLayout::Fan,
			PaperFormat::tryFrom(self::string($query, 'format')) ?? PaperFormat::A1,
			Orientation::tryFrom(self::string($query, 'orientace')) ?? Orientation::Landscape,
			// At least one generation when there is any
			max(min(1, $available), min($available, $depth)),
			PosterStyle::tryFrom(self::string($query, 'styl')) ?? PosterStyle::Simple,
			mb_substr($title, 0, self::MaxTitleLength),
		);
	}

	/**
	 * Whether the query chooses the number of generations (otherwise the poster picks a readable one).
	 *
	 * @param array<mixed> $query
	 */
	public static function hasDepth(array $query): bool
	{
		return ctype_digit(self::string($query, 'generace'));
	}

	public function withDepth(int $depth): self
	{
		return new self($this->layout, $this->format, $this->orientation, $depth, $this->style, $this->title);
	}

	/** Width of the sheet in millimetres. */
	public function width(): int
	{
		[$short, $long] = $this->format->size();

		return $this->orientation === Orientation::Landscape ? $long : $short;
	}

	/** Height of the sheet in millimetres. */
	public function height(): int
	{
		[$short, $long] = $this->format->size();

		return $this->orientation === Orientation::Landscape ? $short : $long;
	}

	/** Name of the CSS page (@page) with this paper size. */
	public function pageName(): string
	{
		return 'poster-' . $this->format->value . '-' . ($this->orientation === Orientation::Landscape ? 'landscape' : 'portrait');
	}

	/** @param array<mixed> $query */
	private static function string(array $query, string $key): string
	{
		$value = $query[$key] ?? '';

		return is_string($value) ? $value : '';
	}
}
