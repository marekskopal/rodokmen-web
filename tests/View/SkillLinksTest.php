<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rodokmen\Site\View\SkillLinks;

final class SkillLinksTest extends TestCase
{
	/** @return iterable<string, array{string, string|null}> */
	public static function links(): iterable
	{
		yield 'person' => ['/osoba/jan-novak-1990/', '/osoba/jan-novak-1990'];
		yield 'person with anchor' => ['/osoba/jan-novak-1990/#zapisy', '/osoba/jan-novak-1990#zapisy'];
		yield 'record' => ['/zdroj/praha_krest/', '/zapis/praha_krest'];
		yield 'line' => ['/rod/novakovi/', '/rod/novakovi'];
		yield 'story' => ['/pribehy/', '/pribeh'];
		yield 'page with query' => ['/generace/?rod=novakovi', '/generace?rod=novakovi'];
		yield 'already local form' => ['/osoba/jan-novak-1990', '/osoba/jan-novak-1990'];
		yield 'home' => ['/', '/'];
		yield 'list of lines is the home page' => ['/rody/#po-otci', '/#po-otci'];
		yield 'family has no page' => ['/rodina/jan-a-marie/', null];
		yield 'family list stays' => ['/rodina/', '/rodina'];
		yield 'external' => ['https://example.org/osoba/x/', 'https://example.org/osoba/x/'];
		yield 'protocol relative' => ['//example.org/zdroj/x/', '//example.org/zdroj/x/'];
		yield 'anchor only' => ['#kapitola', '#kapitola'];
	}

	#[DataProvider('links')]
	public function testRewrite(string $url, ?string $expected): void
	{
		self::assertSame($expected, SkillLinks::rewrite($url));
	}
}
