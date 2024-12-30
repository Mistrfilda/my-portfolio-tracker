<?php

declare(strict_types = 1);

namespace App\Test\Unit\Helper;

use App\Utils\TypeValidator;
use ArrayIterator;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class TypeValidatorTest extends TestCase
{

	public function testValidateString(): void
	{
		$this->assertSame('test', TypeValidator::validateString('test'));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateString(123);
	}

	public function testValidateNullableString(): void
	{
		$this->assertSame('test', TypeValidator::validateNullableString('test'));
		$this->assertNull(TypeValidator::validateNullableString(null));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateNullableString(123);
	}

	public function testValidateInt(): void
	{
		$this->assertSame(123, TypeValidator::validateInt('123'));
		$this->assertSame(123, TypeValidator::validateInt(123));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateInt('123abc');
	}

	public function testValidateNullableInt(): void
	{
		$this->assertSame(123, TypeValidator::validateNullableInt('123'));
		$this->assertNull(TypeValidator::validateNullableInt(null));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateNullableInt('123abc');
	}

	public function testValidateFloat(): void
	{
		$this->assertSame(1.23, TypeValidator::validateFloat('1.23'));
		$this->assertSame(1.23, TypeValidator::validateFloat(1.23));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateFloat('1.23abc');
	}

	public function testValidateNullableFloat(): void
	{
		$this->assertSame(1.23, TypeValidator::validateNullableFloat('1.23'));
		$this->assertNull(TypeValidator::validateNullableFloat(null));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateNullableFloat('1.23abc');
	}

	public function testValidateBool(): void
	{
		$this->assertTrue(TypeValidator::validateBool(true));
		$this->assertTrue(TypeValidator::validateBool('true'));
		$this->assertTrue(TypeValidator::validateBool(1));
		$this->assertTrue(TypeValidator::validateBool('1'));
		$this->assertFalse(TypeValidator::validateBool(false));
		$this->assertFalse(TypeValidator::validateBool('false'));
		$this->assertFalse(TypeValidator::validateBool(0));
		$this->assertFalse(TypeValidator::validateBool('0'));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateBool('yes');
	}

	public function testValidateNullableBool(): void
	{
		$this->assertTrue(TypeValidator::validateNullableBool(true));
		$this->assertFalse(TypeValidator::validateNullableBool(false));
		$this->assertNull(TypeValidator::validateNullableBool(null));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateNullableBool('yes');
	}

	public function testValidateImmutableDatetime(): void
	{
		$dateTime = new ImmutableDateTime('2023-12-01 12:00:00');
		$this->assertSame($dateTime, TypeValidator::validateImmutableDatetime($dateTime));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateImmutableDatetime('2023-12-01 12:00:00');
	}

	public function testValidateNullableImmutableDatetime(): void
	{
		$dateTime = new ImmutableDateTime('2023-12-01 12:00:00');
		$this->assertSame($dateTime, TypeValidator::validateNullableImmutableDatetime($dateTime));
		$this->assertNull(TypeValidator::validateNullableImmutableDatetime(null));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateNullableImmutableDatetime('2023-12-01 12:00:00');
	}

	public function testValidateArray(): void
	{
		$this->assertSame(['a', 'b'], TypeValidator::validateArray(['a', 'b']));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateArray('not an array');
	}

	public function testValidateIterable(): void
	{
		$array = ['key' => 'value'];
		$iterable = new ArrayIterator($array);

		$this->assertSame($array, TypeValidator::validateIterable($array));
		$this->assertSame($iterable, TypeValidator::validateIterable($iterable));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateIterable('not an iterable');
	}

	public function testValidateInstanceOf(): void
	{
		$dateTime = new ImmutableDateTime('2023-12-01 12:00:00');
		$this->assertSame($dateTime, TypeValidator::validateInstanceOf($dateTime, ImmutableDateTime::class));

		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateInstanceOf('not an object', ImmutableDateTime::class);
	}

}
