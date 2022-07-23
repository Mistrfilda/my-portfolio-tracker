<?php

declare(strict_types = 1);

namespace App\UI\Base;

use Nette\Bridges\ApplicationLatte\Template;
use stdClass;

abstract class BasePresenterTemplate extends Template
{

	public string $baseUrl;

	public string $basePath;

	/** @var array<stdClass> */
	public array $flashes;

	public bool $includeBody;

	public string $pageTitle;

}
