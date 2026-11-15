<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Export;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Export\FactParser;
use Rodokmen\Site\Export\GedcomEvent;

final class FactParserTest extends TestCase
{
	/** @return iterable<string, array{string, string|null, GedcomEvent|null}> */
	public static function facts(): iterable
	{
		yield 'birth with a house number' => [
			'* 14. 2. 1890 Holice čp. 112',
			null, new GedcomEvent('BIRT', '14 FEB 1890', 'Holice', '* 14. 2. 1890 Holice čp. 112')];

		yield 'death' => ['† 1944 Holice', null, new GedcomEvent('DEAT', '1944', 'Holice', '')];
		yield 'marriage with the partner' => [
			'× 20. 6. 1919 Holice Josefa Pokorná',
			'Josefa Pokorná', new GedcomEvent('MARR', '20 JUN 1919', 'Holice', '× 20. 6. 1919 Holice Josefa Pokorná')];

		yield 'marriage with a comma' => [
			'× 2015 Praha, Marie Malá',
			'Marie Malá', new GedcomEvent('MARR', '2015', 'Praha', '× 2015 Praha, Marie Malá')];

		yield 'date only' => ['† před 1740', null, new GedcomEvent('DEAT', 'BEF 1740', '', '')];
		yield 'place only' => ['* Holice', null, new GedcomEvent('BIRT', null, 'Holice', '')];
		yield 'source in parentheses' => [
			'* 1862 Ostřetín čp. 23 (podle stromu)',
			null, new GedcomEvent('BIRT', '1862', 'Ostřetín', '* 1862 Ostřetín čp. 23 (podle stromu)')];

		yield 'baptism' => ['pokřtěn 15. 2. 1890 Holice', null, new GedcomEvent('CHR', '15 FEB 1890', 'Holice', '')];
		yield 'a year in a house number is not a date' => ['* Holice 1234', null, new GedcomEvent('BIRT', null, 'Holice 1234', '')];
		yield 'trade' => ['kovář', null, null];
		yield 'residence' => ['bydlel v Holicích čp. 5', null, null];
	}

	#[DataProvider('facts')]
	public function testEvent(string $fact, ?string $partner, ?GedcomEvent $event): void
	{
		self::assertEquals($event, FactParser::event($fact, $partner));
	}

	/** @return iterable<string, array{string, array{string, string}}> */
	public static function names(): iterable
	{
		yield 'given and surname' => ['Jan Novák', ['Jan', 'Novák']];
		yield 'two given names' => ['Jan Nepomuk Novák', ['Jan Nepomuk', 'Novák']];
		yield 'years in parentheses' => ['Petr Novák (* 1993)', ['Petr', 'Novák']];
		yield 'unsure' => ['Anna Malá (?)', ['Anna', 'Malá']];
		yield 'birth surname' => ['Marie Nováková roz. Malá', ['Marie', 'Malá']];
		yield 'one word' => ['Matouš', ['Matouš', '']];
	}

	/** @param array{string, string} $expected */
	#[DataProvider('names')]
	public function testName(string $name, array $expected): void
	{
		self::assertSame($expected, FactParser::name($name));
	}

	/** @return iterable<string, array{string, array{string|null, string|null}}> */
	public static function lifespans(): iterable
	{
		yield 'both' => ['1890–1944', ['1890', '1944']];
		yield 'spaced' => ['1890 – 1944', ['1890', '1944']];
		yield 'birth only' => ['* 1895', ['1895', null]];
		yield 'death only' => ['† 1940', [null, '1940']];
		yield 'about' => ['asi 1720–1785', ['ABT 1720', '1785']];
		yield 'before' => ['1680 – před 1740', ['1680', 'BEF 1740']];
		yield 'living or unknown' => ['1990 – ?', ['1990', null]];
		yield 'a year' => ['1993', ['1993', null]];
		yield 'nothing' => ['', [null, null]];
	}

	/** @param array{string|null, string|null} $expected */
	#[DataProvider('lifespans')]
	public function testLifespan(string $years, array $expected): void
	{
		self::assertSame($expected, FactParser::lifespan($years));
	}
}
