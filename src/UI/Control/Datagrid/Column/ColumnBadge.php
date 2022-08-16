<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Column;

use App\Doctrine\Entity;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Icon\SvgIcon;
use Nette\Utils\Callback;
use function sprintf;

class ColumnBadge extends ColumnText
{

	public const TEMPLATE_FILE = __DIR__ . '/templates/columnBadge.latte';

	/** @var callable(Entity $entity): string|null */
	protected $colorCallback;

	/** @var callable(Entity $entity): SvgIcon|null */
	protected $svgIconCallback;

	public function __construct(
		Datagrid $datagrid,
		string $label,
		string $column,
		protected string $color,
		callable|null $getterMethod = null,
		callable|null $colorCallback = null,
		callable|null $svgIconCallback = null,
	)
	{
		parent::__construct($datagrid, $label, $column, $getterMethod);
		$this->colorCallback = $colorCallback;
		$this->svgIconCallback = $svgIconCallback;
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

	public function getColorClasses(Entity $entity): string
	{
		$colorTemplate = 'bg-%s-100 text-%s-600';
		if ($this->colorCallback !== null) {
			$callback = Callback::check($this->getColorCallback());
			$color = $callback($entity);

			return sprintf($colorTemplate, $color, $color);
		}

		return sprintf($colorTemplate, $this->color, $this->color);
	}

	public function hasSvgIconCallback(): bool
	{
		return $this->svgIconCallback !== null;
	}

	public function getSvgIconFromCallback(Entity $entity): SvgIcon
	{
		$callback = Callback::check($this->svgIconCallback);

		return $callback($entity);
	}

}
