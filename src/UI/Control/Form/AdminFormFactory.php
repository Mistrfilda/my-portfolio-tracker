<?php

declare(strict_types = 1);

namespace App\UI\Control\Form;

class AdminFormFactory
{

	public function __construct(private AdminFormRenderer $adminFormRenderer)
	{

	}

	public function create(string|null $mappedClass = null): AdminForm
	{
		$form = new AdminForm();
		if ($mappedClass !== null) {
			$form->setMappedType($mappedClass);
		}

		$form->setRenderer($this->adminFormRenderer);

		return $form;
	}

}
