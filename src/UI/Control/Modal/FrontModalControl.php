<?php

declare(strict_types = 1);

namespace App\UI\Control\Modal;

use App\UI\Base\BaseControl;
use Nette\HtmlStringable;
use Nette\Utils\Random;

class FrontModalControl extends BaseControl
{

	private const BASE_TEMPLATE = __DIR__ . '/frontModal.latte';

	protected string $modalId;

	protected string|null $templateFile = null;

	protected string|null $includeTemplateFile = null;

	/** @var array<string|int, mixed> */
	protected array $includedTemplateFileParameters = [];

	private string|null $heading = null;

	private HtmlStringable|null $content = null;

	/** @var array<mixed> */
	private array $additionalParameters = [];

	public function __construct()
	{
		$this->modalId = 'modal-' . Random::generate(4, '0-9');
	}

	/**
	 * @param array<mixed> $additionalParameneters
	 */
	public function setParameters(
		string|null $heading,
		HtmlStringable|null $content,
		array $additionalParameneters = [],
	): void
	{
		$this->heading = $heading;
		$this->content = $content;
		$this->additionalParameters = $additionalParameneters;
	}

	public function render(): void
	{
		$this->getTemplate()->modalId = $this->modalId;
		$this->getTemplate()->heading = $this->heading;
		$this->getTemplate()->content = $this->content;
		$this->getTemplate()->additionalParameters = $this->additionalParameters;
		$this->getTemplate()->originalTemplateFile = $this->getOriginalTemplateFile();
		$this->getTemplate()->includeTemplateFile = $this->includeTemplateFile;
		foreach ($this->includedTemplateFileParameters as $key => $value) {
			$this->getTemplate()->{$key} = $value;
		}

		$this->getTemplate()->setFile($this->getTemplateFile());
		$this->getTemplate()->render();
	}

	public function getModalId(): string
	{
		return $this->modalId;
	}

	public function setTemplateFile(string $templateFile): void
	{
		$this->templateFile = $templateFile;
	}

	public function getIncludeTemplateFile(): string|null
	{
		return $this->includeTemplateFile;
	}

	public function setIncludeTemplateFile(string $includeTemplateFile): void
	{
		$this->includeTemplateFile = $includeTemplateFile;
	}

	public function getIncludedTemplateFileParameters(): mixed
	{
		return $this->includedTemplateFileParameters;
	}

	/**
	 * @param array<string|int, mixed> $includedTemplateFileParameters
	 */
	public function setIncludedTemplateFileParameters(array $includedTemplateFileParameters): void
	{
		$this->includedTemplateFileParameters = $includedTemplateFileParameters;
	}

	protected function getOriginalTemplateFile(): string
	{
		return self::BASE_TEMPLATE;
	}

	protected function getTemplateFile(): string
	{
		if ($this->templateFile === null) {
			return $this->getOriginalTemplateFile();
		}

		return $this->templateFile;
	}

}
