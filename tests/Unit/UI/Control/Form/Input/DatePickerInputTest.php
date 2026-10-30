<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Control\Form\Input;

use App\UI\Control\Form\AdminFormFactory;
use App\UI\Control\Form\AdminFormRenderer;
use App\UI\Control\Form\Input\DatePickerInput;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatePickerInputTest extends TestCase
{

	public function testValidDateIsReturnedAtMidnight(): void
	{
		$input = $this->createInput();
		$input->setValue('2024-02-29');
		$input->validate();

		$value = $input->getValue();
		self::assertInstanceOf(ImmutableDateTime::class, $value);
		self::assertSame('2024-02-29 00:00:00.000000', $value->format('Y-m-d H:i:s.u'));
		self::assertSame([], $input->getErrors());
	}

	#[DataProvider('invalidDates')]
	public function testInvalidDateProducesValidationError(string $value): void
	{
		$input = $this->createInput();
		$input->setValue($value);
		$input->validate();

		self::assertSame(['Zadejte platné datum.'], $input->getErrors());
		self::assertSame($value, $input->getValue());
	}

	/** @return iterable<string, array{string}> */
	public static function invalidDates(): iterable
	{
		yield 'nonexistent day' => ['2026-02-30'];
		yield 'non-leap year' => ['2026-02-29'];
		yield 'invalid month' => ['2026-13-01'];
		yield 'invalid format' => ['not-a-date'];
	}

	public function testOptionalEmptyDateKeepsEmptyValue(): void
	{
		$input = $this->createInput();
		$input->setValue('');
		$input->validate();

		self::assertSame([], $input->getErrors());
		self::assertSame('', $input->getValue());
	}

	public function testNullableEmptyDateReturnsNull(): void
	{
		$input = $this->createInput();
		$input->setNullable();
		$input->setValue('');
		$input->validate();

		self::assertSame([], $input->getErrors());
		self::assertNull($input->getValue());
	}

	public function testRequiredEmptyDateProducesRequiredError(): void
	{
		$input = $this->createInput();
		$input->setRequired('Datum je povinné.');
		$input->setValue('');
		$input->validate();

		self::assertSame(['Datum je povinné.'], $input->getErrors());
	}

	public function testDatetimeDefaultIsConvertedToDate(): void
	{
		$input = $this->createInput();
		$input->setValue(new ImmutableDateTime('2026-10-30 15:32:00'));
		$input->validate();

		$value = $input->getValue();
		self::assertInstanceOf(ImmutableDateTime::class, $value);
		self::assertSame('2026-10-30 00:00:00', $value->format('Y-m-d H:i:s'));
		self::assertSame([], $input->getErrors());
	}

	private function createInput(): DatePickerInput
	{
		$factory = new AdminFormFactory($this->createStub(AdminFormRenderer::class));
		return $factory->create()->addDatePicker('date', 'Datum');
	}

}
