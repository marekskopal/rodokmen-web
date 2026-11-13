<?php

declare(strict_types=1);

namespace Rodokmen\Site\Data;

/**
 * Typed reading of decoded JSON: every getter checks the value and throws InvalidDataException on a mismatch,
 * so the readonly models get exactly the types they declare.
 */
final readonly class ArrayReader
{
	/** @param array<mixed> $data */
	private function __construct(private array $data, private string $path)
	{
	}

	public static function of(mixed $data, string $path = '$'): self
	{
		if (!is_array($data)) {
			throw new InvalidDataException(sprintf('%s: expected an object, got %s', $path, get_debug_type($data)));
		}

		return new self($data, $path);
	}

	public function has(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	public function string(string $key, ?string $default = null): string
	{
		$value = $this->value($key, $default);
		if (!is_string($value)) {
			throw $this->invalid($key, 'string', $value);
		}

		return $value;
	}

	public function nullableString(string $key): ?string
	{
		$value = $this->data[$key] ?? null;
		if ($value !== null && !is_string($value)) {
			throw $this->invalid($key, 'string or null', $value);
		}

		return $value;
	}

	public function int(string $key, ?int $default = null): int
	{
		$value = $this->value($key, $default);
		if (!is_int($value)) {
			throw $this->invalid($key, 'int', $value);
		}

		return $value;
	}

	public function nullableInt(string $key): ?int
	{
		$value = $this->data[$key] ?? null;
		if ($value !== null && !is_int($value)) {
			throw $this->invalid($key, 'int or null', $value);
		}

		return $value;
	}

	public function float(string $key): float
	{
		$value = $this->value($key, null);
		if (!is_float($value) && !is_int($value)) {
			throw $this->invalid($key, 'number', $value);
		}

		return (float) $value;
	}

	public function bool(string $key, ?bool $default = null): bool
	{
		$value = $this->value($key, $default);
		if (!is_bool($value)) {
			throw $this->invalid($key, 'bool', $value);
		}

		return $value;
	}

	/** @return list<string> */
	public function stringList(string $key): array
	{
		return array_map(
			fn (mixed $item): string => is_string($item) ? $item : throw $this->invalid($key . '[]', 'string', $item),
			$this->rawList($key),
		);
	}

	/** @return list<int> */
	public function intList(string $key): array
	{
		return array_map(
			fn (mixed $item): int => is_int($item) ? $item : throw $this->invalid($key . '[]', 'int', $item),
			$this->rawList($key),
		);
	}

	/** @return list<float> */
	public function floatList(string $key): array
	{
		return array_map(
			fn (mixed $item): float => is_float($item) || is_int($item) ? (float) $item : throw $this->invalid(
				$key . '[]',
				'number',
				$item,
			),
			$this->rawList($key),
		);
	}

	/** @return list<list<float>> */
	public function floatLists(string $key): array
	{
		$lists = [];
		foreach ($this->list($key) as $index => $row) {
			$lists[] = array_map(
				fn (mixed $item): float => is_float($item) || is_int($item) ? (float) $item : throw $this->invalid(
					$key . '[' . $index . ']',
					'number',
					$item,
				),
				$row->values(),
			);
		}

		return $lists;
	}

	/** @return list<mixed> values of a reader that wraps a JSON list */
	public function values(): array
	{
		return array_values($this->data);
	}

	/** @return list<self> */
	public function list(string $key): array
	{
		$items = [];
		foreach ($this->rawList($key) as $index => $item) {
			$items[] = self::of($item, $this->path . '.' . $key . '[' . $index . ']');
		}

		return $items;
	}

	/** @return array<string, self> */
	public function map(string $key): array
	{
		$items = [];
		foreach ($this->rawMap($key) as $name => $item) {
			$items[(string) $name] = self::of($item, $this->path . '.' . $key . '.' . $name);
		}

		return $items;
	}

	/** @return array<string, string> */
	public function stringMap(string $key): array
	{
		$items = [];
		foreach ($this->rawMap($key) as $name => $item) {
			$items[(string) $name] = is_string($item) ? $item : throw $this->invalid($key . '.' . $name, 'string', $item);
		}

		return $items;
	}

	/** @return array<string, int> */
	public function intMap(string $key): array
	{
		$items = [];
		foreach ($this->rawMap($key) as $name => $item) {
			$items[(string) $name] = is_int($item) ? $item : throw $this->invalid($key . '.' . $name, 'int', $item);
		}

		return $items;
	}

	/** Nested object as a reader (missing key = empty object). */
	public function object(string $key): self
	{
		return self::of($this->data[$key] ?? [], $this->path . '.' . $key);
	}

	private function value(string $key, mixed $default): mixed
	{
		if (!array_key_exists($key, $this->data)) {
			return $default ?? throw new InvalidDataException(sprintf('%s.%s: missing', $this->path, $key));
		}

		return $this->data[$key];
	}

	/** @return list<mixed> */
	private function rawList(string $key): array
	{
		$value = $this->data[$key] ?? [];
		if (!is_array($value) || !array_is_list($value)) {
			throw $this->invalid($key, 'list', $value);
		}

		return $value;
	}

	/** @return array<mixed> JSON objects; an empty one is exported by Python as [] */
	private function rawMap(string $key): array
	{
		$value = $this->data[$key] ?? [];
		if (!is_array($value)) {
			throw $this->invalid($key, 'object', $value);
		}

		return $value;
	}

	private function invalid(string $key, string $expected, mixed $value): InvalidDataException
	{
		return new InvalidDataException(sprintf('%s.%s: expected %s, got %s', $this->path, $key, $expected, get_debug_type($value)));
	}
}
