<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

use Rodokmen\Site\Config;
use Rodokmen\Site\Data\Model\Document;
use Rodokmen\Site\Data\Model\Family;
use Rodokmen\Site\Data\Model\Hypothesis;
use Rodokmen\Site\Data\Model\Issue;
use Rodokmen\Site\Data\Model\Line;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Model\Place;
use Rodokmen\Site\Data\Model\Record;
use Rodokmen\Site\Data\Model\SiteConfig;
use Rodokmen\Site\Data\Model\SiteData;
use Rodokmen\Site\Data\Model\Stats;

/**
 * Read-only access to the exported family tree data (var/data/site.json + markdown notes).
 *
 * The JSON is produced by the genealogy skill's exporter (tools/export_web.py) and copied
 * here by bin/rodokmen-sync; SiteDataLoader turns it into readonly models.
 */
final class Repository
{
	private const DefaultLineColor = '#9a8f7d';

	private ?SiteData $data = null;

	/** @var array<string, list<Place>>|null */
	private ?array $placesByPerson = null;

	public function __construct(private readonly Config $config, private readonly SiteDataLoader $loader)
	{
	}

	public function dataDir(): string
	{
		return $this->config->dataDir;
	}

	public function isAvailable(): bool
	{
		return $this->data !== null || is_file($this->config->dataDir . '/site.json');
	}

	/** Used by the layout, so it must not fail even when the data is missing (error pages). */
	public function siteConfig(): SiteConfig
	{
		return $this->isAvailable() ? $this->data()->config : new SiteConfig('Rodokmen', '', '');
	}

	public function title(): string
	{
		$title = $this->siteConfig()->title;

		return $title !== '' ? $title : 'Rodokmen';
	}

	public function stats(): Stats
	{
		return $this->data()->stats;
	}

	public function rootSlug(): string
	{
		return $this->data()->root;
	}

	/** @return array<string, Person> */
	public function people(): array
	{
		return $this->data()->people;
	}

	public function person(?string $slug): ?Person
	{
		if ($slug === null || $slug === '') {
			return null;
		}

		$data = $this->data();

		return $data->people[$data->aliases[$slug] ?? $slug] ?? null;
	}

	public function family(?string $id): ?Family
	{
		return $id !== null ? ($this->data()->families[$id] ?? null) : null;
	}

	/** @return list<Record> */
	public function records(): array
	{
		return array_values($this->data()->records);
	}

	public function record(string $key): ?Record
	{
		return $this->data()->records[$key] ?? null;
	}

	/**
	 * Records in the given order, unknown keys skipped.
	 *
	 * @param list<string> $keys
	 * @return list<Record>
	 */
	public function recordsByKeys(array $keys): array
	{
		$records = [];
		foreach ($keys as $key) {
			$record = $this->record($key);
			if ($record !== null) {
				$records[] = $record;
			}
		}

		return $records;
	}

	/** @return array<string, Line> */
	public function lines(): array
	{
		return $this->data()->lines;
	}

	public function line(string $slug): ?Line
	{
		return $this->lines()[$slug] ?? null;
	}

	public function lineByNumber(?int $k): ?Line
	{
		foreach ($this->lines() as $line) {
			if ($line->k === $k) {
				return $line;
			}
		}

		return null;
	}

	public function lineColor(?int $k): string
	{
		return $this->lineByNumber($k)->color ?? self::DefaultLineColor;
	}

	/** @return list<Place> */
	public function places(): array
	{
		return $this->data()->places;
	}

	/** @return list<Place> */
	public function placesOf(string $slug): array
	{
		if ($this->placesByPerson === null) {
			$this->placesByPerson = [];
			foreach ($this->places() as $place) {
				foreach ($place->lines as $group) {
					foreach ($group->people as $personSlug) {
						$this->placesByPerson[$personSlug][] = $place;
					}
				}
			}
		}

		return $this->placesByPerson[$slug] ?? [];
	}

	/** @return list<string> */
	public function placeNamesOf(string $slug): array
	{
		return array_map(static fn (Place $place): string => $place->name, $this->placesOf($slug));
	}

	/** @return list<Document> */
	public function documents(): array
	{
		return $this->data()->documents;
	}

	/** @return list<Issue> */
	public function issues(): array
	{
		return $this->data()->issues;
	}

	/** @return list<Hypothesis> */
	public function hypotheses(): array
	{
		return $this->data()->hypotheses;
	}

	/** Markdown note exported by the skill: zajimavosti, pribeh, denik, dotazy. */
	public function markdown(string $name): ?string
	{
		if (preg_match('/^[a-z]+$/', $name) !== 1) {
			return null;
		}

		$file = $this->config->dataDir . '/md/' . $name . '.md';
		if (!is_file($file)) {
			return null;
		}

		$text = file_get_contents($file);
		if ($text === false) {
			return null;
		}

		$text = trim($text);

		return $text !== '' ? $text : null;
	}

	/**
	 * Ancestors of a person as generations: [0 => [slug], 1 => [father, mother], 2 => [...4], ...].
	 * Unknown ancestors are null.
	 *
	 * @return non-empty-list<list<string|null>>
	 */
	public function ancestorGenerations(string $slug, int $depth): array
	{
		$generations = [[$slug]];
		for ($level = 1; $level <= $depth; $level++) {
			$row = [];
			foreach ($generations[$level - 1] as $childSlug) {
				$child = $this->person($childSlug);
				$row[] = $child?->father;
				$row[] = $child?->mother;
			}

			if (array_filter($row, static fn (?string $parent): bool => $parent !== null) === []) {
				break;
			}

			$generations[] = $row;
		}

		return $generations;
	}

	/** Number of known ancestors above the person (for "more generations" hints). */
	public function ancestorCount(string $slug): int
	{
		$count = 0;
		$queue = [$slug];
		$seen = [];
		while ($queue !== []) {
			$person = $this->person(array_shift($queue));
			foreach ([$person?->father, $person?->mother] as $parentSlug) {
				if ($parentSlug === null || isset($seen[$parentSlug])) {
					continue;
				}

				$seen[$parentSlug] = true;
				$count++;
				$queue[] = $parentSlug;
			}
		}

		return $count;
	}

	/**
	 * Descendant chain from the person down to the target (default person), using the exported
	 * paths to the tree root. Returns null when the target is not a descendant.
	 *
	 * @return list<string>|null
	 */
	public function pathBetween(string $ancestor, string $descendant): ?array
	{
		$path = $this->person($ancestor)->path ?? [];
		$index = array_search($descendant, $path, true);

		return $index === false ? null : array_slice($path, 0, $index + 1);
	}

	private function data(): SiteData
	{
		return $this->data ??= $this->loader->load();
	}
}
