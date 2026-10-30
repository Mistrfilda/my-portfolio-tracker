<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\Input;

use Mistrfilda\Datetime\DatetimeException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Forms\Controls\TextInput;

class DatePickerInput extends TextInput
{

	private const DATE_FORMAT = 'Y-m-d';

	public function __construct(string|null $caption = null)
	{
		parent::__construct($caption);
		$this->addRule(
			static fn (self $input): bool => $input->getValue() instanceof ImmutableDateTime,
			'Zadejte platné datum.',
		);
	}

	public function getValue(): mixed
	{
		$value = parent::getValue();
		if (is_string($value) === false) {
			return $value;
		}

		try {
			return DatetimeFactory::createFromFormat(
				$value,
				self::DATE_FORMAT,
			)->setTime(0, 0, 0);
		} catch (DatetimeException) {
			return $value;
		}
	}

	/**
	 * @return static
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
	 */
	public function setValue(mixed $value)
	{
		if ($value instanceof ImmutableDateTime) {
			return parent::setValue($value->format('Y-m-d'));
		}

		return parent::setValue($value);
	}

}
