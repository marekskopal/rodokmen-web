<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use function count;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Short lifespan for the fan chart: "1746 – 1801" → "1746–1801", "? – před 1785" → "† před 1785",
 * "? – po 1849" → "žil 1849", "* 1931" → "* 1931", "1774 – ?" → "1774–?".
 */
final readonly class FanYears
{
	private const Dash = '–';

	public function format(string $y): string
	{
		$y = trim($y);
		if (preg_match_all('/\d{4}/', $y, $m) === 0) {
			return '';
		}

		$years = $m[0];
		$dash = strpos($y, self::Dash);
		$head = $dash === false ? $y : substr($y, 0, $dash);
		$tail = $dash === false ? '' : substr($y, $dash + strlen(self::Dash));
		$last = $years[count($years) - 1];

		if ($this->birthUnknown($y, $head, $dash !== false)) {
			return $this->deathOnly($y, $tail, $last);
		}
		if (str_starts_with($y, '*')) {
			return '* ' . $years[0];
		}

		$approx = preg_match('/^(asi|≈)/u', $y) === 1 ? '≈' : '';
		if (count($years) === 1) {
			return $approx . $years[0] . ($dash !== false ? '–?' : '');
		}

		return $approx . $years[0] . '–' . $this->death($tail, $last);
	}

	private function birthUnknown(string $y, string $head, bool $hasDash): bool
	{
		return str_starts_with($y, '?')
			|| str_starts_with($y, '†')
			|| ($hasDash && preg_match('/\d/', $head) !== 1)
			|| preg_match('/^po\b/u', $y) === 1;
	}

	private function deathOnly(string $y, string $tail, string $last): string
	{
		if (preg_match('/\bpo\b/u', $tail !== '' ? $tail : $y) === 1) {
			return 'žil ' . $last;
		}

		return '† ' . (str_contains($y, 'před') ? 'před ' : '') . $last;
	}

	private function death(string $tail, string $last): string
	{
		if (str_contains($tail, 'před')) {
			return '?';
		}

		return (preg_match('/\bpo\b/u', $tail) === 1 ? 'po ' : '')
			. (preg_match('/(asi|≈)/u', $tail) === 1 ? '≈' : '')
			. $last;
	}
}
