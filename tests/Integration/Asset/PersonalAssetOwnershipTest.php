<?php

declare(strict_types = 1);

namespace App\Test\Integration\Asset;

use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\JobRequest\JobRequestFacade;
use App\Portu\Asset\PortuAssetFacade;
use App\Portu\Asset\PortuAssetRepository;
use App\Portu\Position\PortuPositionFacade;
use App\Portu\Position\UI\PortuPositionFormFactory;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SnapshotFactory;
use App\Stock\Asset\Watchlist\Exception\StockAssetWatchlistTickerAlreadyExistsException;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Asset\Watchlist\StockAssetWatchlistFacade;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\Test\Integration\IntegrationTestCase;
use App\UI\Base\BaseAdminPresenter;
use Closure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Bootstrap\Configurator;
use Nette\Http\IRequest;
use Nette\Http\Request as HttpRequest;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\TestWith;
use Ramsey\Uuid\Uuid;

class PersonalAssetOwnershipTest extends IntegrationTestCase
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
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
		$this->fixture = new PositionOwnershipFixture($this->getService(EntityManagerInterface::class));
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

	public function testPortuListsAndIdLookupsOnlyReturnCurrentOwnersPortfolios(): void
	{
		$repository = $this->getService(PortuAssetRepository::class);
		foreach ([$this->fixture->portu, $this->fixture->otherPortu] as $asset) {
			$this->currentAdmin->setApiAppAdmin($asset->getAppAdmin());
			self::assertSame($asset, $repository->getById($asset->getId()));
			self::assertSame([$asset], $repository->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult());
			self::assertSame([$asset], $repository->findByIds([
				$this->fixture->portu->getId(), $this->fixture->otherPortu->getId()->toString(),
			]));
		}
	}

	public function testPortuCreateAndUpdatePreserveOwnerInDatabase(): void
	{
		$facade = $this->getService(PortuAssetFacade::class);
		$asset = $facade->create('My portfolio', CurrencyEnum::CZK);
		$facade->update($asset->getId(), 'Renamed portfolio', CurrencyEnum::CZK);
		$this->getService(EntityManagerInterface::class)->clear();
		$reloaded = $this->getService(PortuAssetRepository::class)->getById($asset->getId());

		self::assertSame('Renamed portfolio', $reloaded->getName());
		self::assertTrue($this->fixture->admins[0]->getId()->equals($reloaded->getAppAdmin()->getId()));
	}

	public function testForeignPortfolioCannotBeReadUpdatedOrUsedForPositions(): void
	{
		$foreignId = $this->fixture->otherPortu->getId();
		$date = $this->createDatetime('2026-01-15');
		self::assertTrue($this->fixture->admins[0]->isSysAdmin());
		$operations = [
			fn () => $this->getService(PortuAssetRepository::class)->getById($foreignId),
			fn () => $this->getService(PortuAssetFacade::class)->update($foreignId, 'Changed', CurrencyEnum::EUR),
			fn () => $this->getService(PortuPositionFacade::class)->create(
				$foreignId,
				$date,
				100.0,
				10.0,
				120.0,
				110.0,
			),
			fn () => $this->getService(PortuPositionFacade::class)->update(
				$this->fixture->portuPositions[0]->getId(),
				$foreignId,
				$date,
				100.0,
				10.0,
				120.0,
				110.0,
			),
			fn () => $this->getService(PortuPositionFormFactory::class)->create(
				null,
				$foreignId,
				static function (): void {
				},
			),
		];
		foreach ($operations as $operation) {
			$this->assertNotFound($operation);
		}

		self::assertSame('Other ownership Portu', $this->fixture->otherPortu->getName());
		self::assertSame($this->fixture->portu, $this->fixture->portuPositions[0]->getPortuAsset());
	}

	public function testWatchlistTickerCanBeUsedByDifferentOwners(): void
	{
		$items = $this->createWatchlistItems();
		$repository = $this->getService(StockAssetWatchlistRepository::class);
		foreach ($items as $index => $item) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			self::assertSame($item, $repository->getById($item->getId()));
			self::assertSame($item, $repository->findByTicker('AAPL'));
			self::assertSame([$item], $repository->findAll());
			self::assertSame([$item], $repository->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult());
		}
	}

	public function testWatchlistRejectsDuplicateTickerWithinSameOwner(): void
	{
		$this->createWatchlistItems();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$this->expectException(StockAssetWatchlistTickerAlreadyExistsException::class);

		$this->getService(StockAssetWatchlistFacade::class)->create('Duplicate', ' aapl ', null, null);
	}

	public function testDatabaseRejectsDuplicateTickerWithinSameOwner(): void
	{
		$items = $this->createWatchlistItems();
		$entityManager = $this->getService(EntityManagerInterface::class);
		$entityManager->persist(new StockAssetWatchlist(
			$items[0]->getAppAdmin(),
			'Duplicate',
			'AAPL',
			null,
			null,
			$this->createDatetime(),
		));
		$this->expectException(UniqueConstraintViolationException::class);

		$entityManager->flush();
	}

	public function testWatchlistUpdateAndDeleteOnlyAffectOwnedItem(): void
	{
		$items = $this->createWatchlistItems();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$facade = $this->getService(StockAssetWatchlistFacade::class);
		$updated = $facade->update($items[0]->getId(), 'Microsoft', ' msft ', 200.0, CurrencyEnum::USD);
		self::assertSame('MSFT', $updated->getTicker());
		self::assertSame($this->fixture->admins[0], $updated->getAppAdmin());
		self::assertSame('AAPL', $items[1]->getTicker());

		$facade->delete($updated->getId());
		$repository = $this->getService(StockAssetWatchlistRepository::class);
		self::assertSame([], $repository->findAll());
		self::assertSame([$items[1]], $repository->findAllForAppAdmin($this->fixture->admins[1]));
	}

	public function testForeignWatchlistCannotBeReadUpdatedOrDeleted(): void
	{
		$items = $this->createWatchlistItems();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$repository = $this->getService(StockAssetWatchlistRepository::class);
		$facade = $this->getService(StockAssetWatchlistFacade::class);
		$operations = [
			static fn () => $repository->getById($items[1]->getId()),
			static fn () => $facade->update($items[1]->getId(), 'Changed', 'AAPL', null, null),
			static fn () => $facade->delete($items[1]->getId()),
		];
		foreach ($operations as $operation) {
			$this->assertNotFound($operation);
		}

		self::assertSame('Owner 2', $repository->getByIdForAppAdmin(
			$items[1]->getId(),
			$this->fixture->admins[1],
		)->getName());
	}

	public function testExplicitOwnerQueriesWorkWithoutLogin(): void
	{
		$items = $this->createWatchlistItems();
		$this->currentAdmin->beginApiRequest();
		$portuRepository = $this->getService(PortuAssetRepository::class);
		$watchlistRepository = $this->getService(StockAssetWatchlistRepository::class);
		$owner = $this->fixture->admins[1];

		self::assertSame([$this->fixture->otherPortu], $portuRepository->findAllForAppAdmin($owner));
		self::assertSame($this->fixture->otherPortu, $portuRepository->getByIdForAppAdmin(
			$this->fixture->otherPortu->getId(),
			$owner,
		));
		self::assertSame([$items[1]], $watchlistRepository->findAllForAppAdmin($owner));
		self::assertSame($items[1], $watchlistRepository->findByTickerForAppAdmin('AAPL', $owner));
	}

	/** @param class-string<PortuAssetRepository|StockAssetWatchlistRepository> $repositoryClass */
	#[TestWith([PortuAssetRepository::class])]
	#[TestWith([StockAssetWatchlistRepository::class])]
	public function testCurrentOwnerQueryRequiresLogin(string $repositoryClass): void
	{
		$this->currentAdmin->beginApiRequest();
		$this->expectException(AppAdminNotLoggedInException::class);

		$this->getService($repositoryClass)->createQueryBuilderForCurrentAppAdmin();
	}

	public function testNewAiSnapshotOnlyReceivesCurrentUsersSimpleWatchlist(): void
	{
		$items = $this->createWatchlistItems();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$promptGenerator = $this->createStub(StockAiAnalysisPromptGenerator::class);
		$promptGenerator->method('getAutomaticPortfolioData')->willReturn([]);
		$promptGenerator->method('getAutomaticWatchlistData')->willReturn([]);
		$settings = $this->createStub(StockAiAnalysisSettingsFacade::class);
		$settings->method('getInvestorInstructions')->willReturn('');
		$factory = new StockAiAnalysisV2SnapshotFactory(
			$promptGenerator,
			$this->getService(StockAssetWatchlistRepository::class),
			$settings,
		);

		$snapshot = $factory->create(
			Uuid::uuid4(),
			$this->createDatetime(),
			false,
			true,
			false,
			null,
			null,
			null,
			null,
			$this->fixture->admins[0],
		);

		self::assertSame([$items[0]->getId()->toString()], array_column($snapshot['simpleWatchlist'], 'stockAssetId'));
	}

	#[TestWith(['PortuAssetEdit', 'default'])]
	#[TestWith(['PortuPosition', 'positions'])]
	#[TestWith(['PortuPositionEdit', 'edit'])]
	#[TestWith(['StockAssetWatchlist', 'edit'])]
	#[TestWith(['StockAssetWatchlist', 'default', 'delete'])]
	public function testForeignUrlsAndDeleteSignalReturnNotFound(
		string $presenterName,
		string $action,
		string|null $signal = null,
	): void
	{
		$items = $this->createWatchlistItems();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);

		$method = $signal === null ? 'GET' : 'POST';
		$this->presenter($presenterName, $method)->run(new Request('Admin:' . $presenterName, $method, [
			'action' => $action,
			'id' => ($presenterName === 'StockAssetWatchlist' ? $items[1]->getId() : $this->fixture->otherPortu->getId())->toString(),
			'portuAssetId' => $this->fixture->otherPortu->getId()->toString(),
			'portuPositionId' => $this->fixture->portuPositions[0]->getId()->toString(),
			'do' => $signal,
		]));
	}

	#[TestWith(['PortuAsset', 'default'])]
	#[TestWith(['PortuAssetEdit', 'default'])]
	#[TestWith(['PortuPositionEdit', 'edit'])]
	#[TestWith(['StockAssetWatchlist', 'default'])]
	#[TestWith(['StockAssetWatchlist', 'edit'])]
	public function testRegularUserCanOpenOwnPages(string $presenterName, string $action): void
	{
		$items = $this->createWatchlistItems();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		self::assertFalse($this->fixture->admins[1]->isSysAdmin());
		$response = $this->presenter($presenterName)->run(new Request('Admin:' . $presenterName, 'GET', [
			'action' => $action,
			'id' => ($presenterName === 'StockAssetWatchlist' ? $items[1]->getId() : $this->fixture->otherPortu->getId())->toString(),
			'portuAssetId' => $this->fixture->otherPortu->getId()->toString(),
			'portuPositionId' => $this->fixture->portuPositions[1]->getId()->toString(),
		]));

		self::assertInstanceOf(TextResponse::class, $response);
	}

	/** @return array{StockAssetWatchlist, StockAssetWatchlist} */
	private function createWatchlistItems(): array
	{
		$facade = $this->getService(StockAssetWatchlistFacade::class);
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$first = $facade->create('Owner 1', ' aapl ', 150.0, CurrencyEnum::USD);
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$second = $facade->create('Owner 2', 'AAPL', 160.0, CurrencyEnum::USD);
		return [$first, $second];
	}

	private function presenter(string $name, string $method = 'GET'): BaseAdminPresenter
	{
		foreach ($this->container->findByType(IRequest::class) as $serviceName) {
			$this->container->removeService($serviceName);
			$this->container->addService($serviceName, new HttpRequest(
				new UrlScript('http://localhost/'),
				headers: ['Sec-Fetch-Site' => 'same-origin'],
				method: $method,
			));
		}

		$presenter = $this->getService(IPresenterFactory::class)->createPresenter('Admin:' . $name);
		assert($presenter instanceof BaseAdminPresenter);
		$presenter->autoCanonicalize = false;
		return $presenter;
	}

	private function assertNotFound(Closure $operation): void
	{
		try {
			$operation();
		} catch (NoEntityFoundException) {
			self::assertTrue(true);
			return;
		}

		self::fail('Access to another owners record should be rejected.');
	}

}
