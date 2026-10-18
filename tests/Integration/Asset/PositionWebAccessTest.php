<?php

declare(strict_types = 1);

namespace App\Test\Integration\Asset;

use App\Admin\CurrentAppAdminGetter;
use App\Doctrine\NoEntityFoundException;
use App\JobRequest\JobRequestFacade;
use App\Test\Integration\IntegrationTestCase;
use App\UI\Base\BaseAdminPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Bootstrap\Configurator;
use PHPUnit\Framework\Attributes\TestWith;

class PositionWebAccessTest extends IntegrationTestCase
{

	private PositionOwnershipFixture $fixture;

	private CurrentAppAdminGetter $currentAdmin;

	protected function configureContainer(Configurator $configurator): void
	{
		$configurator->addConfig(['session' => ['autoStart' => 'never']]);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixture = new PositionOwnershipFixture($this->getService(EntityManagerInterface::class));
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		foreach ($this->container->findByType(JobRequestFacade::class) as $serviceName) {
			$this->container->removeService($serviceName);
			$this->container->addService($serviceName, $this->createStub(JobRequestFacade::class));
		}
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	#[TestWith(['StockPositionEdit', 'default', 'id', 'stock'])]
	#[TestWith(['StockPositionEdit', 'closePosition', 'stockPositionId', 'stock'])]
	#[TestWith(['CryptoPositionEdit', 'default', 'id', 'crypto'])]
	#[TestWith(['CryptoPositionEdit', 'closePosition', 'cryptoPositionId', 'crypto'])]
	#[TestWith(['PortuPositionEdit', 'edit', 'portuPositionId', 'portu'])]
	#[TestWith(['PortuPositionPrice', 'prices', 'portuPositionId', 'portu'])]
	#[TestWith(['PortuPositionPrice', 'editPrice', 'portuPositionId', 'portu'])]
	public function testForeignPositionUrlsReturnNotFound(
		string $presenterName,
		string $action,
		string $parameter,
		string $assetType,
	): void
	{
		$id = match ($assetType) {
			'stock' => $this->fixture->stockPositions[1]->getId(),
			'crypto' => $this->fixture->cryptoPositions[1]->getId(),
			'portu' => $this->fixture->portuPositions[1]->getId(),
		};
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);

		$this->presenter($presenterName)->run(new Request('Admin:' . $presenterName, 'GET', [
			'action' => $action, $parameter => $id->toString(), 'portuAssetId' => $this->fixture->portu->getId()->toString(),
		]));
	}

	public function testForeignPortuPriceDefaultsReturnNotFound(): void
	{
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);

		$this->presenter('PortuPositionPrice')->run(new Request('Admin:PortuPositionPrice', 'GET', [
			'action' => 'editPrice',
			'portuPositionId' => $this->fixture->portuPositions[0]->getId()->toString(),
			'previousPortuPositionId' => $this->fixture->portuPrices[1]->getId(),
		]));
	}

	public function testMissingPositionHasSameNotFoundResponse(): void
	{
		$missingId = '00000000-0000-4000-8000-000000000000';
		try {
			$this->presenter('StockPositionEdit')->run(new Request('Admin:StockPositionEdit', 'GET', [
				'action' => 'default', 'id' => $missingId,
			]));
			self::fail('Missing position should return HTTP 404.');
		} catch (BadRequestException $exception) {
			self::assertSame(404, $exception->getCode());
			self::assertInstanceOf(NoEntityFoundException::class, $exception->getPrevious());
		}
	}

	public function testOwnPortuPriceDefaultsCanBeOpened(): void
	{
		$response = $this->presenter('PortuPositionPrice')->run(new Request('Admin:PortuPositionPrice', 'GET', [
			'action' => 'editPrice',
			'portuPositionId' => $this->fixture->portuPositions[0]->getId()->toString(),
			'previousPortuPositionId' => $this->fixture->portuPrices[0]->getId(),
		]));

		self::assertInstanceOf(TextResponse::class, $response);
	}

	private function presenter(string $name): BaseAdminPresenter
	{
		$presenter = $this->getService(IPresenterFactory::class)->createPresenter('Admin:' . $name);
		assert($presenter instanceof BaseAdminPresenter);
		$presenter->autoCanonicalize = false;
		return $presenter;
	}

}
