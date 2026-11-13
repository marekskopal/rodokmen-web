<?php

declare(strict_types=1);

namespace Rodokmen\Site;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Tiny autowiring PSR-11 container: shared instances, constructor injection by class type.
 */
final class Container implements ContainerInterface
{
	/** @var array<string, object> */
	private array $instances = [];

	public function set(string $id, object $instance): void
	{
		$this->instances[$id] = $instance;
	}

	public function has(string $id): bool
	{
		return isset($this->instances[$id]) || class_exists($id);
	}

	/**
	 * Typed variant of get() for class names.
	 *
	 * @param class-string<T> $class
	 * @return T
	 * @template T of object
	 */
	public function service(string $class): object
	{
		$service = $this->get($class);
		if (!$service instanceof $class) {
			throw new RuntimeException(sprintf('Service %s is not an instance of itself', $class));
		}

		return $service;
	}

	public function get(string $id): object
	{
		if (isset($this->instances[$id])) {
			return $this->instances[$id];
		}

		if (!class_exists($id)) {
			throw new class (sprintf('Service %s not found', $id)) extends RuntimeException implements NotFoundExceptionInterface {
			};
		}

		$reflection = new ReflectionClass($id);
		$constructor = $reflection->getConstructor();
		$arguments = [];

		foreach ($constructor?->getParameters() ?? [] as $parameter) {
			$type = $parameter->getType();
			if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				$arguments[] = $this->get($type->getName());
				continue;
			}

			if ($parameter->isDefaultValueAvailable()) {
				$arguments[] = $parameter->getDefaultValue();
				continue;
			}

			throw new RuntimeException(sprintf('Cannot autowire parameter $%s of %s', $parameter->getName(), $id));
		}

		return $this->instances[$id] = $reflection->newInstanceArgs($arguments);
	}
}
