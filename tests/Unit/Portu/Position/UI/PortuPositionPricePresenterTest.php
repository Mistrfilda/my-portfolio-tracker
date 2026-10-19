<?php

declare(strict_types = 1);

namespace App\Test\Unit\Portu\Position\UI;

use App\Portu\Position\PortuPositionRepository;
use App\Portu\Position\UI\PortuPositionPriceFormFactory;
use App\Portu\Position\UI\PortuPositionPriceGridFactory;
use App\Portu\Position\UI\PortuPositionPricePresenter;
use App\Router\RouterFactory;
use App\UI\Control\Form\AdminForm;
use Nette\Application\IPresenterFactory;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\Url;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PortuPositionPricePresenterTest extends TestCase
{

	#[TestWith([395])]
	#[TestWith([null])]
	public function testFormActionPreservesPositionParameters(int|null $previousPortuPositionId): void
	{
		$portuPositionId = '00000000-0000-4000-8000-000000000001';
		$form = new AdminForm();
		$formFactory = $this->createStub(PortuPositionPriceFormFactory::class);
		$formFactory->method('create')->willReturn($form);
		$presenter = new PortuPositionPricePresenter(
			$formFactory,
			$this->createStub(PortuPositionRepository::class),
			$this->createStub(PortuPositionPriceGridFactory::class),
		);
		$presenter->injectPrimary(
			new Request(new UrlScript('http://localhost/')),
			$this->createStub(IResponse::class),
			$this->createStub(IPresenterFactory::class),
			RouterFactory::createRouter(),
		);
		$presenter->setParent(null, 'Admin:PortuPositionPrice');
		$presenter->changeAction('editPrice');
		$presenter->loadState([
			'portuPositionId' => $portuPositionId,
			'previousPortuPositionId' => $previousPortuPositionId,
		]);
		$presenter->getComponent('portuPositionPriceForm');

		$action = new Url($form->getAction());

		self::assertSame('/portu-position-price/edit-price', $action->getPath());
		self::assertSame($portuPositionId, $action->getQueryParameter('portuPositionId'));
		self::assertSame(
			$previousPortuPositionId === null ? null : (string) $previousPortuPositionId,
			$action->getQueryParameter('previousPortuPositionId'),
		);
	}

}
