<?php

declare(strict_types = 1);

namespace App\Utils;

use Nette\Http\IRequest;

class PublicDirPathGetter
{

	private IRequest $request;

	private string $wwwDir;

	public function __construct(string $wwwDir, IRequest $request)
	{
		$this->wwwDir = $wwwDir;
		$this->request = $request;
	}

	public function getBasePath(): string
	{
		return rtrim($this->request->getUrl()->getBasePath(), '/');
	}

	public function getBaseUrl(): string
	{
		return rtrim($this->request->getUrl()->getBaseUrl(), '/');
	}

	public function getWwwDir(): string
	{
		return $this->wwwDir;
	}

}
