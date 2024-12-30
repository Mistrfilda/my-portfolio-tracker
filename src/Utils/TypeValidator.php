<?php

declare(strict_types = 1);

namespace App\Utils;

use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class TypeValidator
{

	public static function validateString(mixed $value): string
	{
		if (!is_string($value)) {
			throw new InvalidArgumentException('Expected a string, but received ' . gettype($value));
		}

		return $value;
	}

	public static function validateNullableString(mixed $value): string|null
	{
		if ($value === null) {
			return null;
		}

		return self::validateString($value);
	}

	public static function validateInt(mixed $value): int
	{
		if (is_numeric($value)) {
			return (int) $value;
		}

		throw new InvalidArgumentException('Expected an integer, but received ' . gettype($value));
	}

	public static function validateNullableInt(mixed $value): int|null
	{
		if ($value === null) {
			return null;
		}

		return self::validateInt($value);
	}

	public static function validateFloat(mixed $value): float
	{
		if (is_numeric($value)) {
			return (float) $value;
		}

		throw new InvalidArgumentException('Expected a float, but received ' . gettype($value));
	}

	public static function validateNullableFloat(mixed $value): float|null
	{
		if ($value === null) {
			return null;
		}

		return self::validateFloat($value);
	}

	public static function validateBool(mixed $value): bool
	{
		$trueValues = ['true', '1', 1, true];
		$falseValues = ['false', '0', 0, false];

		if (in_array($value, $trueValues, true)) {
			return true;
		}

		if (in_array($value, $falseValues, true)) {
			return false;
		}

		throw new InvalidArgumentException(
			sprintf(
				'Expected a boolean-like value, but received %s with value: %s',
				gettype($value),
				var_export($value, true),
			),
		);
	}

	public static function validateNullableBool(mixed $value): bool|null
	{
		if ($value === null) {
			return null;
		}

		return self::validateBool($value);
	}

	public static function validateImmutableDatetime(mixed $value): ImmutableDateTime
	{
		return self::validateInstanceOf($value, ImmutableDateTime::class);
	}

	public static function validateNullableImmutableDatetime(mixed $value): ImmutableDateTime|null
	{
		if ($value === null) {
			return null;
		}

		return self::validateImmutableDatetime($value);
	}

	/**
	 * @return array<mixed>
	 */
	public static function validateArray(mixed $value): array
	{
		if (!is_array($value)) {
			throw new InvalidArgumentException('Expected an array, but received ' . gettype($value));
		}

		return $value;
	}

	/**
	 * @return iterable<mixed>
	 */
	public static function validateIterable(mixed $value): iterable
	{
		if (!is_iterable($value)) {
			throw new InvalidArgumentException('Expected an iterable, but received ' . gettype($value));
		}

		return $value;
	}

	/**
	 * Validates that the given value is of the expected class type.
	 *
	 * @template T of object
	 * @param mixed $value The value to validate.
	 * @param class-string<T> $className The expected class name.
	 * @return T
	 * @throws InvalidArgumentException If the value is not an instance of the expected class.
	 */
	public static function validateInstanceOf(mixed $value, string $className): object
	{
		if (!$value instanceof $className) {
			throw new InvalidArgumentException(
				'Expected instance of ' . $className . ', but received ' . (is_object($value) ? $value::class : gettype(
					$value,
				)),
			);
		}

		return $value;
	}

}
