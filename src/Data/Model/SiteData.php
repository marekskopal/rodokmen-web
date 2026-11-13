<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Everything exported to site.json by tools/export_web.py. */
final readonly class SiteData
{
	public function __construct(
		public SiteConfig $config,
		/** @var list<Place> */
		public array $places,
		/** Slug of the tree root (person 1). */
		public string $root,
		/** @var array<string, string> old slug => current slug */
		public array $aliases,
		/** @var array<string, Person> */
		public array $people,
		/** @var array<string, Family> */
		public array $families,
		/** @var array<string, Record> */
		public array $records,
		/** @var array<string, Line> */
		public array $lines,
		/** @var list<Issue> */
		public array $issues,
		public Stats $stats,
		/** @var list<Hypothesis> */
		public array $hypotheses,
		/** @var list<Document> */
		public array $documents,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			SiteConfig::fromReader($r->object('config')),
			array_map(Place::fromReader(...), $r->list('places')),
			$r->string('root'),
			$r->stringMap('aliases'),
			array_map(Person::fromReader(...), $r->map('people')),
			array_map(Family::fromReader(...), $r->map('families')),
			self::recordsByKey($r),
			array_map(Line::fromReader(...), $r->map('lines')),
			array_map(Issue::fromReader(...), $r->list('issues')),
			Stats::fromReader($r->object('stats')),
			array_map(Hypothesis::fromReader(...), $r->list('hypotheses')),
			array_map(Document::fromReader(...), $r->list('docs')),
		);
	}

	/** @return array<string, Record> in export order */
	private static function recordsByKey(ArrayReader $r): array
	{
		$records = [];
		foreach ($r->list('records') as $item) {
			$record = Record::fromReader($item);
			$records[$record->key] = $record;
		}

		return $records;
	}
}
