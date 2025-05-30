<?php

declare(strict_types = 1);

namespace App\UI\Base;

use App\Admin\CurrentAppAdminGetter;
use Nette\Application\BadRequestException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\Presenter;
use Nette\Bridges\SecurityHttp\SessionStorage;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionMethod;

abstract class BasePresenter extends Presenter
{

	protected BasePresenterParameters $basePresenterParameters;

	protected CurrentAppAdminGetter $currentAppAdminGetter;

	public function injectBasePresenterParameters(BasePresenterParameters $basePresenterParameters): void
	{
		$this->basePresenterParameters = $basePresenterParameters;
	}

	public function injectCurrentAppAdminGetter(CurrentAppAdminGetter $currentAppAdminGetter): void
	{
		$this->currentAppAdminGetter = $currentAppAdminGetter;
	}

	public function startup(): void
	{
		parent::startup();
		$this->template->includeBody = false;
		$this->template->heading = null;
		$this->template->pageTitle = $this->basePresenterParameters->getPageTitle();
	}

	/**
	 * @param array<string> $links
	 * @throws InvalidLinkException
	 */
	public function isMenuLinkActive(array $links): bool
	{
		foreach ($links as $link) {
			if ($this->isLinkCurrent($link)) {
				return true;
			}
		}

		return false;
	}

	protected function processParameterIntId(): int
	{
		$id = $this->getParameter('id');
		if (is_scalar($id) === false || (int) $id === 0) {
			throw new BadRequestException('Missing parameter ID');
		}

		return (int) $id;
	}

	protected function processParameterBool(string $parameterName = 'id'): bool|null
	{
		$id = $this->getParameter($parameterName);
		if (is_scalar($id) === false) {
			return null;
		}

		return (bool) (int) $id;
	}

	protected function processParameterInt(string $parameterName = 'id'): int|null
	{
		$id = $this->getParameter($parameterName);
		if (is_scalar($id) === false) {
			return null;
		}

		return (int) $id;
	}

	protected function processRequiredParameterInt(string $parameterName = 'id'): int
	{
		$id = $this->getParameter($parameterName);
		if (is_scalar($id) === false) {
			throw new BadRequestException('Missing parameter ID');
		}

		return (int) $id;
	}

	protected function processParameterStringId(string $parameterName = 'id'): string
	{
		$id = $this->getParameter($parameterName);
		if (is_scalar($id) === false || (string) $id === '') {
			throw new BadRequestException('Missing parameter ID');
		}

		return (string) $id;
	}

	protected function processParameterRequiredUuid(string $parameterName = 'id'): UuidInterface
	{
		$id = $this->getParameter($parameterName);
		if (is_scalar($id) === false || (string) $id === '') {
			throw new BadRequestException('Missing parameter ID');
		}

		return Uuid::fromString((string) $id);
	}

	protected function processParameterUuid(string $parameterName = 'id'): UuidInterface|null
	{
		$id = $this->getParameter($parameterName);
		if (is_scalar($id) === false || (string) $id === '') {
			return null;
		}

		return Uuid::fromString((string) $id);
	}

	protected function createUuidFromString(string $id): UuidInterface
	{
		return Uuid::fromString($id);
	}

	public function invalidatePage(): void
	{
		if ($this->isAjax()) {
			$this->redrawControl();
		} else {
			$this->redirect('this');
		}
	}

	/**
	 * @phpstan-ignore-next-line
	 */
	public function checkRequirements(ReflectionClass|ReflectionMethod $element): void
	{
		$storage = $this->getUser()->getStorage();
		assert($storage instanceof SessionStorage);

		$storage->setNamespace($this->basePresenterParameters->getStorageName());
		parent::checkRequirements($element);
	}

}
