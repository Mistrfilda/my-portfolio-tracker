<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Column;

use App\Doctrine\IEntity;
use App\UI\Control\Datagrid\Datagrid;
use Nette\Utils\Callback;
use function sprintf;

class ColumnBadge extends ColumnText
{

	public const TEMPLATE_FILE = __DIR__ . '/templates/columnBadge.latte';

	/** @var callable|null */
	protected $colorCallback;

	public function __construct(
		Datagrid $datagrid,
		string $label,
		string $column,
		protected string $color,
		callable|null $getterMethod = null,
		callable|null $colorCallback = null,
	)
	{
		parent::__construct($datagrid, $label, $column, $getterMethod);
		$this->colorCallback = $colorCallback;
	}

	public function getColor(): string
	{
		return $this->color;
	}

	public function getTemplate(): string
	{
		return self::TEMPLATE_FILE;
	}

	public function getColorCallback(): callable|null
	{
		return $this->colorCallback;
	}

	public function isNull(string $value): bool
	{
		return $value === Datagrid::NULLABLE_PLACEHOLDER;
	}

	public function getColorClasses(IEntity $entity): string
	{
		$colorTemplate = 'bg-%s-100 text-%s-600';
		if ($this->colorCallback !== null) {
			$callback = Callback::check($this->getColorCallback());
			$color = $callback($entity);

			return sprintf($colorTemplate, $color, $color);
		}

		return sprintf($colorTemplate, $this->color, $this->color);
	}

}
