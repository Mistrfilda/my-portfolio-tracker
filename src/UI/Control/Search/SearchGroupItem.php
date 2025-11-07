<?php

declare(strict_types = 1);

namespace App\UI\Control\Search;

use Nette\Utils\Strings;

class SearchGroupItem
{

	public function __construct(private string $label, private string $link)
	{
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getLink(): string
	{
		return $this->link;
	}

	public function getWebalizedLabel(): string
	{
		return Strings::webalize($this->label);
	}

}
