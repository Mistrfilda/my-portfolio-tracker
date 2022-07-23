<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\Container;

use Nette\Forms\Container;

class AdminFormContainer extends Container
{

	private bool $isTimeContainer = false;

	private string|null $label = null;

	public function isTimeContainer(): bool
	{
		return $this->isTimeContainer;
	}

	public function setTimeContainer(): void
	{
		$this->isTimeContainer = true;
	}

	public function getLabel(): string|null
	{
		return $this->label;
	}

	public function setLabel(string|null $label): void
	{
		$this->label = $label;
	}

}
