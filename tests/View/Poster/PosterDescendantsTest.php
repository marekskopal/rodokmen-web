<?php

declare(strict_types=1);

namespace Rodokmen\Site\Tests\View\Poster;

use PHPUnit\Framework\TestCase;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Tests\Support\Fixture;
use Rodokmen\Site\View\Poster\Orientation;
use Rodokmen\Site\View\Poster\PaperFormat;
use Rodokmen\Site\View\Poster\Poster;
use Rodokmen\Site\View\Poster\PosterLayout;
use Rodokmen\Site\View\Poster\PosterOptions;
use Rodokmen\Site\View\Poster\PosterParts;
use Rodokmen\Site\View\Poster\PosterStyle;

/** The person's descendants down to the website's default person on the trunk of the painted tree. */
final class PosterDescendantsTest extends TestCase
{
	private string $dir;

	private Repository $repository;

	private Poster $poster;

	protected function setUp(): void
	{
		$this->dir = Fixture::dataDir();
		[$this->repository, $this->poster] = PosterTest::poster($this->dir);
	}

	protected function tearDown(): void
	{
		Fixture::removeDir($this->dir);
	}

	public function testChoicesRunFromTheChildToTheDefaultPerson(): void
	{
		$slugs = static fn (array $people): array => array_map(static fn (Person $person): string => $person->slug, $people);

		self::assertSame(
			['jan-novak-1990', 'antonin-novak-2024'],
			$slugs($this->poster->descendantChoices($this->person('josef-novak-1960'))),
		);
		self::assertSame(['antonin-novak-2024'], $slugs($this->poster->descendantChoices($this->person('marie-mala-1992'))));
		self::assertSame([], $this->poster->descendantChoices($this->person('antonin-novak-2024')), 'the default person has none');
	}

	public function testThePaintedTreeShowsTheDescendantsDownToTheChosenOne(): void
	{
		$all = $this->svg(PosterLayout::PaintedTree, 'antonin-novak-2024');
		self::assertStringContainsString('Jan Novák', $all);
		self::assertStringContainsString('Antonín Novák', $all);
		$child = $this->svg(PosterLayout::PaintedTree, 'jan-novak-1990');
		// In the person's colour, like the person: each descendant adds as many of its marks.
		$person = substr_count($child, 'pst-root') - substr_count($this->svg(PosterLayout::PaintedTree, ''), 'pst-root');
		self::assertGreaterThan(0, $person);
		self::assertSame(substr_count($child, 'pst-root') + $person, substr_count($all, 'pst-root'));

		self::assertStringContainsString('Jan Novák', $child);
		self::assertStringNotContainsString('Antonín Novák', $child);

		foreach ([$this->svg(PosterLayout::PaintedTree, ''), $this->svg(PosterLayout::PaintedTree, 'marie-mala-1992')] as $none) {
			self::assertStringNotContainsString('Jan Novák', $none, 'nothing, or nobody of the line, chosen');
		}
		self::assertStringNotContainsString('Jan Novák', $this->svg(PosterLayout::Tree, 'antonin-novak-2024'), 'only the painted tree');
	}

	public function testTheOptionTravelsInTheQuery(): void
	{
		$options = PosterOptions::fromQuery(['potomci' => ' jan-novak-1990 '], 0);

		self::assertSame('jan-novak-1990', $options->descendant);
		self::assertSame('jan-novak-1990', $options->withDepth(0)->descendant);
		self::assertStringContainsString('&potomci=jan-novak-1990', $options->query(false));
		self::assertStringNotContainsString('potomci', PosterOptions::fromQuery([], 0)->query(false));
	}

	private function svg(PosterLayout $layout, string $descendant): string
	{
		$options = new PosterOptions(
			$layout,
			PaperFormat::A2,
			Orientation::Landscape,
			0,
			PosterStyle::Historical,
			'',
			new PosterParts(),
			$descendant,
		);

		return $this->poster->render($this->person('josef-novak-1960'), $options)->svg;
	}

	private function person(string $slug): Person
	{
		$person = $this->repository->person($slug);
		self::assertNotNull($person);

		return $person;
	}
}
