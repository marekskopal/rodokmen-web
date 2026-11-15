<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Export;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Export\GedcomWriter;

final class GedcomWriterTest extends TestCase
{
	public function testLinesRecordsAndPointers(): void
	{
		$out = new GedcomWriter();
		$out->record('I1', 'INDI');
		$out->line(1, 'NAME', 'Jan /Novák/');
		$out->line(1, 'BIRT');
		$out->pointer(1, 'FAMC', 'F1');

		self::assertSame("\u{FEFF}0 @I1@ INDI\r\n1 NAME Jan /Novák/\r\n1 BIRT\r\n1 FAMC @F1@\r\n", $out->toString());
	}

	public function testLineBreaksBecomeContAndAtIsDoubled(): void
	{
		$out = new GedcomWriter();
		$out->line(1, 'NOTE', "první\r\n\nfarář@fara");

		self::assertSame("\u{FEFF}1 NOTE první\r\n2 CONT\r\n2 CONT farář@@fara\r\n", $out->toString());
	}

	public function testLongValuesAreSplitWithConcNeverNextToASpace(): void
	{
		$text = str_repeat('slovo ', 100);
		$out = new GedcomWriter();
		$out->line(1, 'NOTE', $text);

		$lines = explode("\r\n", trim(substr($out->toString(), 3)));
		self::assertGreaterThan(2, count($lines));
		$joined = '';
		foreach ($lines as $i => $line) {
			self::assertLessThanOrEqual(255, mb_strlen($line));
			self::assertMatchesRegularExpression($i === 0 ? '/^1 NOTE /' : '/^2 CONC \S/u', $line);
			self::assertDoesNotMatchRegularExpression('/ $/', $line);
			$joined .= $i === 0 ? substr($line, 7) : substr($line, 7);
		}
		self::assertSame(rtrim($text), $joined);
	}

	public function testAnEscapedAtIsNotSplitByConc(): void
	{
		$out = new GedcomWriter();
		$out->line(1, 'NOTE', str_repeat('x', 199) . '@y');

		self::assertSame("\u{FEFF}1 NOTE " . str_repeat('x', 199) . "\r\n2 CONC @@y\r\n", $out->toString());
	}
}
