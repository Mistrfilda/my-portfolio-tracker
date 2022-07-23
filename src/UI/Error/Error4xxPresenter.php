<?php

declare(strict_types = 1);

namespace App\UI\Error;

use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use function is_file;

final class Error4xxPresenter extends Presenter
{

	public function startup(): void
	{
		parent::startup();
		if ($this->getRequest() !== null && !$this->getRequest()->isMethod(Request::FORWARD)) {
			$this->error();
		}
	}

	public function renderDefault(BadRequestException $exception): void
	{
		$template = $this->getTemplate();
		if (!$template instanceof Template) {
			throw new BadRequestException();
		}

		$code = $exception->getCode();
		$file = __DIR__ . "/templates/$code.latte";
		$template->setFile(is_file($file) ? $file : __DIR__ . '/templates/4xx.latte');
		// load template 403.latte or 404.latte or ... 4xx.latte
	}

}
