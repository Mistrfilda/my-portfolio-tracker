<?php

declare(strict_types = 1);

namespace App\UI\Control\Form;

use Nette\Application\UI\TemplateFactory;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Forms\Form;
use Nette\Forms\FormRenderer;
use function assert;

class AdminFormRenderer implements FormRenderer
{

	public const TEMPLATE_FILE = __DIR__ . '/templates/adminForm.latte';

	private TemplateFactory $templateFactory;

	public function __construct(TemplateFactory $templateFactory)
	{
		$this->templateFactory = $templateFactory;
	}

	public function render(Form $form): string
	{
		$template = $this->templateFactory->createTemplate();
		assert($template instanceof Template);
		$template->setFile(self::TEMPLATE_FILE);
		$template->form = $form;

		return (string) $template;
	}

}
