<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterParts;

final class PosterPartsTest extends TestCase
{
	public function testShowsEverythingWithoutTheFormsMarker(): void
	{
		$parts = PosterParts::fromQuery(['nadpis-zobrazit' => '1']);

		foreach ([$parts->heading(), $parts->person(), $parts->generations(), $parts->footer(), $parts->name(), $parts->date(), $parts->legend()] as $shown) {
			self::assertTrue($shown);
		}
		self::assertSame([], $parts->query(), 'nothing to say in a link');
	}

	public function testTheFormTurnsOffWhatIsNotTicked(): void
	{
		$parts = PosterParts::fromQuery(['casti' => '1', 'nadpis-zobrazit' => '1', 'nadpis-generace' => '1', 'paticka-datum' => '1']);

		self::assertTrue($parts->heading());
		self::assertFalse($parts->person());
		self::assertTrue($parts->generations());
		self::assertFalse($parts->footer());
		self::assertFalse($parts->date(), 'only with the footer');
		self::assertTrue($parts->ticked('paticka-datum'), 'but the box stays ticked for when the footer comes back');
		self::assertFalse($parts->ticked('paticka-legenda'));
		self::assertSame(['casti' => '1', 'nadpis-zobrazit' => '1', 'nadpis-generace' => '1', 'paticka-datum' => '1'], $parts->query());
	}

	public function testPartsGoWithTheHeading(): void
	{
		$parts = PosterParts::fromQuery(['casti' => '1', 'nadpis-osoba' => '1', 'nadpis-generace' => '1', 'paticka-zobrazit' => '1']);

		self::assertFalse($parts->heading());
		self::assertFalse($parts->person());
		self::assertFalse($parts->generations());
		self::assertTrue($parts->footer());
		self::assertFalse($parts->name());
	}

	public function testOptionsKeepThePartsInTheirQuery(): void
	{
		$options = PosterOptions::fromQuery(['casti' => '1', 'nadpis-zobrazit' => '1'], 3)->withDepth(2);

		self::assertFalse($options->parts->footer());
		self::assertStringEndsWith('&casti=1&nadpis-zobrazit=1', $options->query(true));
		self::assertStringNotContainsString('casti', PosterOptions::fromQuery([], 3)->query(true));
	}
}
