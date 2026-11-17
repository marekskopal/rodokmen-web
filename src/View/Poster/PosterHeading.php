<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\HomePerson;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Export\FactParser;

/**
 * Default heading of the poster: the website's title for its own person ("Rodokmen Skopalových"), and the family
 * of anybody else the poster is drawn for ("Rodokmen Rybkových" for Miloslav Rybka).
 */
final readonly class PosterHeading
{
	private const Vowels = 'aáeéěiíoóuúůyý';

	/** Consonants that a final -ě softens when the ending is dropped (Purkyně → Purkyňových). */
	private const Softened = ['d' => 'ď', 't' => 'ť', 'n' => 'ň'];

	public function __construct(private Repository $repository, private HomePerson $homePerson)
	{
	}

	public function of(Person $root): string
	{
		// The website's own person: the one the tree was exported for, or the one set as the default (SITE_DEFAULT_PERSON).
		$own = $root->slug === $this->repository->rootSlug() || $root->slug === $this->homePerson->defaultSlug();
		$family = $own ? '' : $this->family(FactParser::name($root->name)[1]);

		return $family !== '' ? 'Rodokmen ' . $family : $this->repository->title();
	}

	/**
	 * "Of the family": the genitive plural of a Czech surname, male or female – Novák and Nováková → Novákových,
	 * Rybka → Rybkových, Veselý and Veselá → Veselých, Krejčí → Krejčích, Hájek → Hájkových, Němec → Němcových.
	 * Empty for a name without a surname and for anything but a single word of letters ("N.", "ml.", "Nováková-Malá"),
	 * which then keeps the site's title. Surnames that lose their -e- in other endings (Havel → Havlových) keep it
	 * here; the visitor can always write the heading.
	 */
	public function family(string $surname): string
	{
		$surname = trim($surname);
		$stem = static fn (int $drop): string => mb_substr($surname, 0, mb_strlen($surname) - $drop);
		$last = mb_strtolower(mb_substr($surname, -1));

		return match (true) {
			mb_strlen($surname) < 2, preg_match('/^\p{L}+$/u', $surname) !== 1 => '',
			$this->endsWith($surname, 'ová') => $stem(3) . 'ových',
			$last === 'ý' || $last === 'á' => $stem(1) . 'ých',
			$last === 'í' => $stem(1) . 'ích',
			// Janů, Petrů: already "of the family"
			$last === 'ů' => $surname,
			$last === 'ě' => $this->soften($stem(1)) . 'ových',
			$last === 'a' || $last === 'o' || $last === 'e' => $stem(1) . 'ových',
			// Vaněk → Vaňkových, as Vaňková
			$this->softensBeforeK($surname) => $this->soften($stem(2)) . 'kových',
			$this->losesE($surname) => $stem(2) . mb_substr($surname, -1) . 'ových',
			default => $surname . 'ových',
		};
	}

	/**
	 * Hájek → Hájk-, Němec → Němc-: the -e- of -ek goes unless the name is too short to do without it
	 * (Šnek → Šnek-), that of -ec only after a single consonant (Kadlec → Kadlec-).
	 */
	private function losesE(string $surname): bool
	{
		if (mb_strlen($surname) < 4) {
			return false;
		}
		if ($this->endsWith($surname, 'ek')) {
			return mb_strlen($surname) > 4 && !$this->isVowel(mb_substr($surname, -3, 1));
		}

		return $this->endsWith($surname, 'ec')
			&& !$this->isVowel(mb_substr($surname, -3, 1))
			&& $this->isVowel(mb_substr($surname, -4, 1));
	}

	/** -děk, -těk, -něk: the -ě- goes and leaves the consonant soft. */
	private function softensBeforeK(string $surname): bool
	{
		return mb_strlen($surname) > 4
			&& $this->endsWith($surname, 'ěk')
			&& isset(self::Softened[mb_strtolower(mb_substr($surname, -3, 1))]);
	}

	private function soften(string $stem): string
	{
		$last = mb_substr($stem, -1);

		return isset(self::Softened[$last]) ? mb_substr($stem, 0, -1) . self::Softened[$last] : $stem;
	}

	private function endsWith(string $surname, string $ending): bool
	{
		return mb_strtolower(mb_substr($surname, -mb_strlen($ending))) === $ending;
	}

	private function isVowel(string $letter): bool
	{
		return $letter !== '' && mb_strpos(self::Vowels, mb_strtolower($letter)) !== false;
	}
}
