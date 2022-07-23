<?php

declare(strict_types = 1);

namespace App\UI\Base;

class BaseFrontPresenter extends BasePresenter
{

	protected bool $useFullHeight = false;

	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->useFullHeight = $this->useFullHeight;
	}

	/**
	 * @return array<string>
	 */
	public function formatLayoutTemplateFiles(): array
	{
		return array_merge([__DIR__ . '/templates/@frontLayout.latte'], parent::formatLayoutTemplateFiles());
	}

}
