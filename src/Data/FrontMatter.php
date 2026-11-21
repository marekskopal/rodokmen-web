<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use const PREG_SET_ORDER;

/**
 * Markdown with a front matter as the skill writes it (data/pribeh.md):
 *
 *     ---
 *     title: Příběh Novákových
 *     lead: 'Jedna věta, která chytí.'
 *     ---
 *
 * Only flat `key: value` lines are read (plain, 'single' or "double" quoted, or a `>`/`|` block of indented lines,
 * with an optional `# comment`); anything else is ignored.
 */
final readonly class FrontMatter
{
	/** @param array<string, string> $fields */
	private function __construct(public array $fields, public string $body)
	{
	}

	public static function parse(string $markdown): self
	{
		// A file saved with a UTF-8 BOM (older exporters kept it) would hide the front matter.
		if (str_starts_with($markdown, "\u{FEFF}")) {
			$markdown = substr($markdown, 3);
		}
		if (preg_match('/\A---[ \t]*\R(.*?)^---[ \t]*(?:\R|\z)/ms', $markdown, $m) !== 1) {
			return new self([], $markdown);
		}

		preg_match_all(
			'/^([A-Za-z_][\w-]*)[ \t]*:[ \t]*(.*?)[ \t]*\r?$((?:\R[ \t]+\S.*?\r?$|\R[ \t]*\r?$)*)/m',
			$m[1],
			$lines,
			PREG_SET_ORDER,
		);
		$fields = [];
		foreach ($lines as $field) {
			$fields[$field[1]] = preg_match('/\A[>|][+-]?\z/', $field[2]) === 1
				? self::block($field[2][0] === '>', $field[3])
				: self::unquote($field[2]);
		}

		return new self($fields, ltrim(substr($markdown, strlen($m[0])), "\r\n"));
	}

	public function field(string $key): string
	{
		return $this->fields[$key] ?? '';
	}

	private static function unquote(string $value): string
	{
		// Any value may end with a comment (" # …").
		if (preg_match('/\A\'((?:[^\']|\'\')*)\'(?:[ \t]+#.*)?\z/s', $value, $m) === 1) {
			return str_replace("''", "'", $m[1]);
		}
		if (preg_match('/\A"((?:[^"\\\\]|\\\\.)*)"(?:[ \t]+#.*)?\z/s', $value, $m) === 1) {
			return str_replace(['\\"', '\\\\'], ['"', '\\'], $m[1]);
		}

		return (string) preg_replace('/[ \t]+#.*\z/', '', $value);
	}

	/** Block scalar (`lead: >` folded into one line, `lead: |` keeping the lines) from its indented lines. */
	private static function block(bool $folded, string $block): string
	{
		$lines = array_map(trim(...), explode("\n", str_replace("\r", '', trim($block))));

		return $folded ? trim((string) preg_replace('/\s+/', ' ', implode(' ', $lines))) : implode("\n", $lines);
	}
}
