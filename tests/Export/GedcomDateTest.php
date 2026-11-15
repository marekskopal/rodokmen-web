<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Export;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Export\GedcomDate;

final class GedcomDateTest extends TestCase
{
	/** @return iterable<string, array{string, string|null}> */
	public static function dates(): iterable
	{
		yield 'day month year' => ['14. 2. 1890', '14 FEB 1890'];
		yield 'without spaces' => ['1.12.1799', '1 DEC 1799'];
		yield 'month/year' => ['3/1867', 'MAR 1867'];
		yield 'year' => ['1797', '1797'];
		yield 'about' => ['asi 1720', 'ABT 1720'];
		yield 'around' => ['kolem 1700', 'ABT 1700'];
		yield 'before' => ['před 1740', 'BEF 1740'];
		yield 'after a full date' => ['po 3. 4. 1801', 'AFT 3 APR 1801'];
		yield 'invalid month' => ['14. 13. 1890', '(14. 13. 1890)'];
		yield 'phrase' => ['v zimě 1890', '(v zimě 1890)'];
		yield 'qualified phrase' => ['asi v zimě (1890)', '(asi v zimě 1890)'];
		yield 'unknown' => ['?', null];
		yield 'empty' => ['  ', null];
	}

	#[DataProvider('dates')]
	public function testFromCzech(string $czech, ?string $gedcom): void
	{
		self::assertSame($gedcom, GedcomDate::fromCzech($czech));
	}
}
