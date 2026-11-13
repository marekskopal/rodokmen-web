<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\ArrayReader;
use Rodokmen\Site\Data\InvalidDataException;

final class ArrayReaderTest extends TestCase
{
	public function testReadsScalarsWithTheirTypes(): void
	{
		$r = ArrayReader::of(['s' => 'text', 'i' => 7, 'f' => 1.5, 'n' => 3, 'b' => true, 'null' => null]);

		self::assertSame('text', $r->string('s'));
		self::assertSame(7, $r->int('i'));
		self::assertSame(1.5, $r->float('f'));
		self::assertSame(3.0, $r->float('n'));
		self::assertTrue($r->bool('b'));
		self::assertNull($r->nullableString('null'));
		self::assertNull($r->nullableInt('missing'));
		self::assertTrue($r->has('null'));
		self::assertFalse($r->has('missing'));
	}

	public function testMissingKeyUsesDefault(): void
	{
		$r = ArrayReader::of([]);

		self::assertSame('x', $r->string('a', 'x'));
		self::assertSame(0, $r->int('a', 0));
		self::assertFalse($r->bool('a', false));
		self::assertSame([], $r->stringList('a'));
		self::assertSame([], $r->map('a'));
	}

	public function testMissingKeyWithoutDefaultThrows(): void
	{
		$this->expectException(InvalidDataException::class);
		$this->expectExceptionMessageMatches('/' . preg_quote('$.name: missing', '/') . '/');

		ArrayReader::of([])->string('name');
	}

	public function testWrongTypeThrowsWithPath(): void
	{
		$r = ArrayReader::of(['people' => ['a' => ['k' => 'one']]]);

		$this->expectException(InvalidDataException::class);
		$this->expectExceptionMessageMatches('/' . preg_quote('$.people.a.k: expected int, got string', '/') . '/');

		$r->map('people')['a']->int('k');
	}

	public function testRejectsNonArray(): void
	{
		$this->expectException(InvalidDataException::class);

		ArrayReader::of('text');
	}

	public function testLists(): void
	{
		$r = ArrayReader::of(['s' => ['a', 'b'], 'i' => [1, 2], 'f' => [1, 0.5], 'o' => [['x' => 1]], 'boxes' => [[0, 0.5, 1, 1]]]);

		self::assertSame(['a', 'b'], $r->stringList('s'));
		self::assertSame([1, 2], $r->intList('i'));
		self::assertSame([1.0, 0.5], $r->floatList('f'));
		self::assertSame(1, $r->list('o')[0]->int('x'));
		self::assertSame([[0.0, 0.5, 1.0, 1.0]], $r->floatLists('boxes'));
	}

	public function testListRejectsWrongItem(): void
	{
		$this->expectException(InvalidDataException::class);

		ArrayReader::of(['s' => ['a', 1]])->stringList('s');
	}

	public function testListRejectsObject(): void
	{
		$this->expectException(InvalidDataException::class);

		ArrayReader::of(['s' => ['a' => 'b']])->stringList('s');
	}

	public function testMaps(): void
	{
		$r = ArrayReader::of(['aliases' => ['old' => 'new'], 'gens' => ['a' => 1], 'empty' => [], 'cfg' => ['x' => 'y']]);

		self::assertSame(['old' => 'new'], $r->stringMap('aliases'));
		self::assertSame(['a' => 1], $r->intMap('gens'));
		self::assertSame([], $r->stringMap('empty'));
		self::assertSame('y', $r->object('cfg')->string('x'));
		self::assertFalse($r->object('missing')->has('x'));
	}
}
