<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\Data;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\FrontMatter;

final class FrontMatterTest extends TestCase
{
	public function testReadsFieldsAndBody(): void
	{
		$document = FrontMatter::parse(
			"---\ntitle: Příběh Nováků\nlead: 'Ať: to ''chytí'''\nrok: \"18\\\"55\"\n---\n\n## Kapitola\nText.\n",
		);

		self::assertSame('Příběh Nováků', $document->field('title'));
		self::assertSame("Ať: to 'chytí'", $document->field('lead'));
		self::assertSame('18"55', $document->field('rok'));
		self::assertSame('', $document->field('chybi'));
		self::assertSame("## Kapitola\nText.\n", $document->body);
	}

	public function testEmptyValueAndWindowsLineEnds(): void
	{
		$document = FrontMatter::parse("---\r\ntitle: Příběh\r\nlead: ''\r\n---\r\nText");

		self::assertSame(['title' => 'Příběh', 'lead' => ''], $document->fields);
		self::assertSame('Text', $document->body);
	}

	public function testWithoutFrontMatterTheWholeTextIsTheBody(): void
	{
		$text = "# Příběh rodu\n\n---\n\nText.";

		self::assertSame([], FrontMatter::parse($text)->fields);
		self::assertSame($text, FrontMatter::parse($text)->body);
	}

	public function testByteOrderMark(): void
	{
		$document = FrontMatter::parse("\u{FEFF}---\ntitle: Příběh\n---\nText");

		self::assertSame('Příběh', $document->field('title'));
		self::assertSame('Text', $document->body);
	}

	public function testBlockScalarsAndComments(): void
	{
		$document = FrontMatter::parse(
			"---\nlead: >-\n  Jedna věta,\n  která chytí.\n\nbasen: |\n  Řádek\n  druhý\ntitle: Příběh # pracovní\n"
			. "menu: 'Rod # 1' # komentář\n---\nText",
		);

		self::assertSame('Jedna věta, která chytí.', $document->field('lead'));
		self::assertSame("Řádek\ndruhý", $document->field('basen'));
		self::assertSame('Příběh', $document->field('title'));
		self::assertSame('Rod # 1', $document->field('menu'));
		self::assertSame('Text', $document->body);
	}

	public function testUnclosedFrontMatterIsText(): void
	{
		self::assertSame([], FrontMatter::parse("---\ntitle: Příběh\n\nText.")->fields);
	}
}
