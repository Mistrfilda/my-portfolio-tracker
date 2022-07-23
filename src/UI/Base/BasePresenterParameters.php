<?php

declare(strict_types = 1);

namespace App\UI\Base;

class BasePresenterParameters
{

	public function __construct(private string $pageTitle, private string $storageName)
	{
	}

	public function getPageTitle(): string
	{
		return $this->pageTitle;
	}

	public function getStorageName(): string
	{
		return $this->storageName;
	}

}
