<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\Input;

class Multiplier extends \Contributte\FormMultiplier\Multiplier
{

	private string|null $heading = null;

	private string|null $divId = null;

	public function getHeading(): string|null
	{
		return $this->heading;
	}

	public function setHeading(string|null $heading): void
	{
		$this->heading = $heading;
	}

	public function getDivId(): string|null
	{
		return $this->divId;
	}

	public function setDivId(string|null $divId): void
	{
		$this->divId = $divId;
	}

}
