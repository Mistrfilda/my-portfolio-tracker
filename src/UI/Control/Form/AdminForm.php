<?php

declare(strict_types = 1);

namespace App\UI\Control\Form;

use App\UI\Control\Form\Container\AdminFormContainer;
use App\UI\Control\Form\Input\CustomFileUpload;
use App\UI\Control\Form\Input\DatePickerInput;
use App\UI\Control\Form\Input\Multiplier;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SelectBox;
use Nette\Utils\Json;

class AdminForm extends Form
{

	public const SELECT_PLACEHOLDER = '-- vyberte --';

	private bool $isAjax = false;

	private string|null $headingTitle = null;

	private string|null $headingText = null;

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
	 */
	public function addUpload(string $name, $label = null): CustomFileUpload
	{
		return $this[$name] = new CustomFileUpload($label, false);
	}

	public function addAdminContainer(string $name, string $label): AdminFormContainer
	{
		$control = new AdminFormContainer();
		$control->setLabel($label);
		$control->currentGroup = $this->currentGroup;
		if ($this->currentGroup !== null) {
			$this->currentGroup->add($control);
		}

		return $this[$name] = $control;
	}

	public function ajax(): void
	{
		$this->isAjax = true;
	}

	public function setHeading(
		string|null $headingTitle,
		string|null $headingText,
	): void
	{
		$this->headingTitle = $headingTitle;
		$this->headingText = $headingText;
	}

	public function isAjax(): bool
	{
		return $this->isAjax;
	}

	public function hasHeading(): bool
	{
		return $this->headingTitle !== null || $this->headingText !== null;
	}

	public function getHeadingTitle(): string|null
	{
		return $this->headingTitle;
	}

	public function getHeadingText(): string|null
	{
		return $this->headingText;
	}

	public function addDynamic(
		string $name,
		string $heading,
		callable $factory,
		int $copies = 1,
		int|null $maxCopies = null,
	): Multiplier
	{
		$control = new Multiplier($factory, $copies, $maxCopies);
		$control->setCurrentGroup($this->getCurrentGroup());
		$control->setHeading($heading);

		$this[$name] = $control;

		return $control;
	}

	public function addDatePicker(string $name, string $label): DatePickerInput
	{
		$control = new DatePickerInput($label);
		$this[$name] = $control;

		return $control;
	}

	public function formatSelectData(SelectBox $selectBox): string
	{
		$items = $selectBox->getItems();
		if ($selectBox->getPrompt() !== false) {
			$items[null] = $selectBox->getPrompt();
		}

		$defaultValue = $selectBox->getValue();
		if ($defaultValue === null) {
			$defaultValue = $selectBox->getPrompt() !== false ? $selectBox->getPrompt() : '';
		}

		$data = [
			'data' => $items,
			'emptyOptionsMessage' => 'Nebyl nalezen žádný výsledek',
			'name' => $selectBox->getName(),
			'placeholder' => $defaultValue,
			'value' => $selectBox->getValue() === null ? null : (string) $selectBox->getValue(),
		];

		return Json::encode($data);
	}

}
