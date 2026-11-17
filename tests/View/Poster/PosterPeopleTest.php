<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\SiteDataLoader;
use Rodokmen\Site\Tests\Support\DemoFamily;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\Poster\PosterPeople;

final class PosterPeopleTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private PosterPeople $people;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir(DemoFamily::siteData());
		$config = Fixture::config($this->dir);
		$this->repository = new Repository($config, new SiteDataLoader($config));
		$this->people = new PosterPeople($this->repository);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testMenAreBlueAndWomenOldRose(): void
	{
		$shades = ['M' => [], 'F' => []];
		foreach ($this->repository->ancestorGenerations('marek-skopal', 6) as $g => $row) {
			foreach ($row as $i => $slug) {
				$person = $this->repository->person($slug);
				if ($g === 0 || $person === null) {
					continue;
				}

				$shade = $this->people->sexShade($person, $g, $i);
				self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $shade);
				$hue = self::hue($shade);
				$base = $person->isFemale() ? PosterPeople::WomenHue : PosterPeople::MenHue;
				// Turned by the family line at most 20° either way (and a little for the rounding to bytes).
				$apart = abs(fmod($hue - $base + 540, 360) - 180);
				self::assertLessThan(22, $apart, sprintf('%s: %s', $person->name, $shade));
				$shades[$person->isFemale() ? 'F' : 'M'][$person->line ?? 0] = $shade;
			}
		}

		self::assertGreaterThan(1, count(array_unique($shades['M'])), 'the lines give the men different shades');
		self::assertGreaterThan(1, count(array_unique($shades['F'])), 'the lines give the women different shades');
	}

	private static function hue(string $hex): float
	{
		[$r, $g, $b] = array_map(static fn (string $c): float => hexdec($c) / 255, str_split(substr($hex, 1), 2));
		$max = max($r, $g, $b);
		$delta = $max - min($r, $g, $b);
		$hue = match ($max) {
			$r => fmod(($g - $b) / $delta + 6, 6),
			$g => ($b - $r) / $delta + 2,
			default => ($r - $g) / $delta + 4,
		};

		return $hue * 60;
	}
}
