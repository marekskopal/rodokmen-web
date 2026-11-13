<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data\Model;

use Rodokmen\Site\Data\ArrayReader;

/** Parish register entry (or another source) with its crop, transcription and translation. */
final readonly class Record
{
	public function __construct(
		public string $key,
		/** N (birth), O (marriage), Z (death), I (index), S (census) or J (other source). */
		public string $kind,
		public string $date,
		public string $title,
		public string $summary,
		public int $scan,
		public string $archive,
		public string $volume,
		public string $vdesc,
		public string $portal,
		public string $url,
		public bool $img,
		public bool $full,
		/** @var list<Box> */
		public array $boxes,
		public string $orig,
		public string $cs,
		public string $kindName,
		/** @var list<string> */
		public array $people,
	) {
	}

	public static function fromReader(ArrayReader $r): self
	{
		return new self(
			$r->string('key'),
			$r->string('kind'),
			$r->string('date', ''),
			$r->string('title'),
			$r->string('summary', ''),
			$r->int('scan', 0),
			$r->string('archive', ''),
			$r->string('volume', ''),
			$r->string('vdesc', ''),
			$r->string('portal', ''),
			$r->string('url', ''),
			$r->bool('img', false),
			$r->bool('full', false),
			array_map(Box::fromList(...), $r->floatLists('boxes')),
			$r->string('orig', ''),
			$r->string('cs', ''),
			$r->string('kindName', ''),
			$r->stringList('people'),
		);
	}

	/** Year in the date (for ordering), 9999 when unknown. */
	public function year(): int
	{
		return preg_match('/(1[5-9]\d\d|20\d\d)/', $this->date, $m) === 1 ? (int) $m[1] : 9999;
	}
}
