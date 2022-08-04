<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Action;

use App\Doctrine\Entity;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Ramsey\Uuid\UuidInterface;

class DatagridAction implements IDatagridAction
{

	private const TEMPLATE_FILE = __DIR__ . '/templates/datagridAction.latte';

	/** @var callable|null */
	private $conditionCallback;

	/**
	 * @param array<DatagridActionParameter> $parameters
	 */
	public function __construct(
		private Datagrid $datagrid,
		private string $id,
		private string $label,
		private string $destination,
		private array $parameters,
		private SvgIcon|null $icon = null,
		private string $color = TailwindColorConstant::BLUE,
		callable|null $conditionCallback = null,
		private bool $isAjax = false,
		private string|null $confirmationString = null,
	)
	{
		$this->conditionCallback = $conditionCallback;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getIcon(): string|null
	{
		return $this->icon?->value;
	}

	public function getDestination(): string
	{
		return $this->destination;
	}

	/**
	 * @return array<DatagridActionParameter>
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	public function getColor(): string
	{
		return $this->color;
	}

	public function getTemplateFile(): string
	{
		return self::TEMPLATE_FILE;
	}

	public function getDatagrid(): Datagrid
	{
		return $this->datagrid;
	}

	public function getConditionCallback(): callable|null
	{
		return $this->conditionCallback;
	}

	public function setConditionCallback(callable|null $conditionCallback): void
	{
		$this->conditionCallback = $conditionCallback;
	}

	public function isAjax(): bool
	{
		return $this->isAjax;
	}

	public function ajax(): void
	{
		$this->isAjax = true;
	}

	public function setConfirmation(string $confirmation): void
	{
		$this->confirmationString = $confirmation;
	}

	public function getConfirmation(): string|null
	{
		return $this->confirmationString;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function formatParametersForAction(Entity $row): array
	{
		$formatedParameters = [];
		foreach ($this->parameters as $parameter) {
			if ($parameter->getRawValue() !== null) {
				$formatedParameters[$parameter->getParameter()] = $parameter->getRawValue();

				continue;
			}

			$value = $this->datagrid->getDatasource()->getValueForKey(
				$parameter->getReferencedColumn(),
				$row,
			);

			if ($value instanceof UuidInterface) {
				$value = $value->toString();
			}

			$formatedParameters[$parameter->getParameter()] = $value;
		}

		return $formatedParameters;
	}

}
