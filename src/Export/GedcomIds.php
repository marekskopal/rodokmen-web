<?php

declare(strict_types=1);

namespace Rodokmen\Site\Export;

use Rodokmen\Site\Data\Repository;

/**
 * Cross-reference identifiers of the GEDCOM export: ancestors I1…, their full siblings P1…, families F1…, register
 * entries S1…; numbered in the order of the data, so the same data give the same file.
 */
final class GedcomIds
{
	/** @var array<string, string> */
	private array $people = [];

	/** @var array<string, string> */
	private array $families = [];

	/** @var array<string, string> */
	private array $sources = [];

	/** @var array<string, string> */
	private array $siblings = [];

	/** @var array<string, list<string>> person slug => families where the person is a parent */
	private array $parents = [];

	public function __construct(Repository $repository)
	{
		foreach (array_keys($repository->people()) as $i => $slug) {
			$this->people[$slug] = 'I' . ($i + 1);
		}
		foreach (array_values($repository->families()) as $i => $family) {
			$this->families[$family->id] = 'F' . ($i + 1);
			foreach ([$family->father, $family->mother] as $parent) {
				// Under the person's own slug, in case the family names the parent by an alias.
				$slug = $repository->person($parent)?->slug;
				if ($slug !== null) {
					$this->parents[$slug][] = $this->families[$family->id];
				}
			}
		}
		foreach ($repository->records() as $i => $record) {
			$this->sources[$record->key] = 'S' . ($i + 1);
		}
	}

	public function person(string $slug): string
	{
		return $this->people[$slug] ?? 'I0';
	}

	public function family(string $id): string
	{
		return $this->families[$id] ?? 'F0';
	}

	public function source(string $key): string
	{
		return $this->sources[$key] ?? 'S0';
	}

	public function sibling(string $family, int $index): string
	{
		return $this->siblings[$family . '#' . $index] ??= 'P' . (count($this->siblings) + 1);
	}

	/** @return list<string> */
	public function familiesAsParent(string $slug): array
	{
		return $this->parents[$slug] ?? [];
	}
}
