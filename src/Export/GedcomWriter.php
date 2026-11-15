<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

/**
 * Writes GEDCOM 5.5.1 lines: "level [@xref@] TAG [value]". Long values are split with CONC, line breaks become CONT,
 * a literal @ is doubled; the file is UTF-8 with a byte order mark and CRLF line endings.
 */
final class GedcomWriter
{
	/** Characters of a value per line; GEDCOM allows 255 per line including the level and the tag. */
	private const MaxValue = 200;

	/** @var list<string> */
	private array $lines = [];

	/** A record: "0 @xref@ TAG". */
	public function record(string $xref, string $tag): void
	{
		$this->lines[] = '0 @' . $xref . '@ ' . $tag;
	}

	/** A pointer to another record: "level TAG @xref@". */
	public function pointer(int $level, string $tag, string $xref): void
	{
		$this->lines[] = $level . ' ' . $tag . ' @' . $xref . '@';
	}

	/** A line with a text value (may be long or have line breaks). */
	public function line(int $level, string $tag, string $value = ''): void
	{
		$parts = explode("\n", str_replace(["\r\n", "\r"], "\n", str_replace('@', '@@', $value)));
		foreach ($parts as $i => $part) {
			$chunks = $this->chunks($part);
			foreach ($chunks as $j => $chunk) {
				[$lineLevel, $lineTag] = match (true) {
					$i === 0 && $j === 0 => [$level, $tag],
					$j === 0 => [$level + 1, 'CONT'],
					default => [$level + 1, 'CONC'],
				};
				$this->lines[] = rtrim($lineLevel . ' ' . $lineTag . ' ' . $chunk);
			}
		}
	}

	public function toString(): string
	{
		return "\u{FEFF}" . implode("\r\n", $this->lines) . "\r\n";
	}

	/**
	 * Splits a value into pieces of at most MaxValue characters, never next to a space (readers may trim the spaces
	 * at the ends of CONC lines) and never inside an escaped @@.
	 *
	 * @return non-empty-list<string>
	 */
	private function chunks(string $value): array
	{
		$chunks = [];
		while (mb_strlen($value) > self::MaxValue) {
			$cut = self::MaxValue;
			// Nor inside an escaped "@@", which a reader would see as two lone @ signs.
			while ($cut > 1 && self::unsafeCut(mb_substr($value, $cut - 1, 1), mb_substr($value, $cut, 1))) {
				$cut--;
			}

			$chunks[] = mb_substr($value, 0, $cut);
			$value = mb_substr($value, $cut);
		}

		$chunks[] = $value;

		return $chunks;
	}

	private static function unsafeCut(string $before, string $after): bool
	{
		return $before === ' ' || $after === ' ' || ($before === '@' && $after === '@');
	}
}
