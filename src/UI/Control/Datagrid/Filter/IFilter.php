<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

interface IFilter
{

	public function getType(): string;

	public function getKey(): string;

	public function getLabel(): string;

	public function getColumn(): string;

	public function getReferencedColumn(): string|null;

	/**
	 * @return array<string>
	 */
	public function getParameterKeys(): array;

	/**
	 * @return array<string, string|int>
	 */
	public function getValues(): array;

	public function getValue(string $parameter): int|string|null;

	public function setValue(string $parameter, int|string $value): void;

	public function clear(): void;

	public function isValueSet(): bool;

	public function hasParameter(string $parameter): bool;

	public function getActiveValueLabel(): string;

}
