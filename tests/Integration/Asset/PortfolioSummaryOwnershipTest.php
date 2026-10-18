<?php

declare(strict_types = 1);

namespace App\Test\Integration\Asset;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceSummaryFacade;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListDetailControlEnum;
use App\Crypto\Position\Closed\CryptoClosedPositionFacade;
use App\Crypto\Position\CryptoAssetPositionDetailDTO;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Position\CryptoPositionFacade;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Dashboard\DashboardValueBuilderFacade;
use App\Dashboard\DashboardValueGroupEnum;
use App\JobRequest\JobRequestFacade;
use App\Portu\Position\PortuPositionFacade;
use App\Statistic\UI\Chart\PortfolioTotalValueChartProvider;
use App\Statistic\UI\Chart\PortfolioTotalValueLastMonthChartProvider;
use App\Statistic\UI\Chart\StockDividendByCompanyAndMonthChartDataProvider;
use App\Statistic\UI\Chart\StockDividendByCompanyChartDataProvider;
use App\Statistic\UI\Chart\StockDividendByMonthChartDataProvider;
use App\Statistic\UI\Chart\StockDividendByYearChartDataProvider;
use App\Statistic\UI\Chart\TotalInvestedAmountPieChartDataProvider;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockAssetPositionDetailDTO;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Test\Integration\IntegrationTestCase;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Extension\Webpack\WebpackAssetsFactory;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Bootstrap\Configurator;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Http\IRequest;
use Nette\Http\Request as HttpRequest;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\TestWith;
use Ramsey\Uuid\Uuid;

class PortfolioSummaryOwnershipTest extends IntegrationTestCase
{

	private PositionOwnershipFixture $fixture;

	private CurrentAppAdminGetter $currentAdmin;

	private AppAdmin $emptyAdmin;

	protected function configureContainer(Configurator $configurator): void
	{
		$configurator->addConfig(['session' => ['autoStart' => 'never']]);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$em = $this->getService(EntityManagerInterface::class);
		$this->fixture = new PositionOwnershipFixture($em);
		$now = new ImmutableDateTime('2026-01-15');
		$name = 'empty-portfolio-' . Uuid::uuid4()->toString();
		$this->emptyAdmin = new AppAdmin(
			$name,
			$name,
			$name . '@example.test',
			'unused-test-password',
			$now,
			false,
			false,
		);
		$em->persist($this->emptyAdmin);
		$stockPrice = new StockAssetPriceRecord(
			$now,
			CurrencyEnum::USD,
			150,
			$this->fixture->stock,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$now,
		);
		$cryptoPrice = new CryptoAssetPriceRecord($now, CurrencyEnum::USD, 200, $this->fixture->crypto, $now);
		$this->fixture->stock->setCurrentPrice($stockPrice, $now);
		$this->fixture->crypto->setCurrentPrice($cryptoPrice, $now);
		$em->persist($stockPrice);
		$em->persist($cryptoPrice);
		foreach ($this->fixture->admins as $index => $admin) {
			$pieces = ($index + 1) * 10;
			$em->persist(
				new StockPosition(
					$admin,
					$this->fixture->stock,
					$pieces,
					100,
					$now,
					new AssetPriceEmbeddable($pieces * 100, CurrencyEnum::USD),
					false,
					$now,
				),
			);
			$cryptoPieces = ($index + 1) * 0.25;
			$em->persist(
				new CryptoPosition(
					$admin,
					$this->fixture->crypto,
					$cryptoPieces,
					100,
					$now,
					new AssetPriceEmbeddable($cryptoPieces * 100, CurrencyEnum::USD),
					false,
					$now,
				),
			);
		}

		$em->flush();

		$conversionRepository = $this->createStub(CurrencyConversionRepository::class);
		$conversion = static fn (CurrencyEnum $from, CurrencyEnum $to): CurrencyConversion => new CurrencyConversion(
			$from,
			$to,
			$from === CurrencyEnum::USD && $to === CurrencyEnum::CZK ? 20 : 1,
			CurrencySourceEnum::CNB,
			$now,
			$now,
		);
		$conversionRepository->method('getCurrentCurrencyPairConversion')->willReturnCallback($conversion);
		$conversionRepository->method('findCurrencyPairConversionForClosestDate')->willReturnCallback($conversion);
		$this->replaceService(CurrencyConversionRepository::class, $conversionRepository);
		$this->replaceService(JobRequestFacade::class, $this->createStub(JobRequestFacade::class));
		$this->replaceService(WebpackAssetsFactory::class, $this->createStub(WebpackAssetsFactory::class));
		$session = $this->createStub(Session::class);
		$session->method('getSection')->willReturn($this->createStub(SessionSection::class));
		$this->replaceService(Session::class, $session);
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testSharedAssetValuesAndClosedPositionsFollowOwnerInOneProcess(): void
	{
		$stock = $this->getService(StockPositionFacade::class);
		$crypto = $this->getService(CryptoPositionFacade::class);
		$portu = $this->getService(PortuPositionFacade::class);
		$total = $this->getService(AssetPriceSummaryFacade::class);
		foreach ([0, 1, 0] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			$factor = $index + 1;
			$dto = $stock->getStockAssetDetailDTO($this->fixture->stock->getId());
			self::assertSame(10 * $factor, $dto->getPiecesCount());
			self::assertCount(1, $dto->getPositions());
			self::assertSame(1500.0 * $factor, $dto->getCurrentAmount()->getPrice());
			self::assertSame(500.0 * $factor, $dto->getCurrentPriceDiff()->getPriceDifference());
			self::assertSame(100.0, $dto->getAveragePurchasePrice());
			self::assertSame(
				30000.0 * $factor,
				$stock->getCurrentPortfolioValueInUsdStocks(CurrencyEnum::CZK)->getPrice(),
			);
			self::assertSame(0.0, $stock->getCurrentPortfolioValueInCzechStocks(CurrencyEnum::CZK)->getPrice());
			$closed = $stock->getStockAssetDetailDTO(
				$this->fixture->stock->getId(),
				StockAssetListDetailControlEnum::CLOSED_POSITIONS,
			);
			self::assertSame(10 * $factor, $closed->getPiecesCount());
			self::assertSame(1100.0 * $factor, $closed->getCurrentAmount()->getPrice());
			self::assertSame(100.0 * $factor, $closed->getCurrentPriceDiff()->getPriceDifference());
			$closedGroups = $this->getService(StockClosedPositionFacade::class)->getAllStockClosedPositions();
			self::assertCount(1, $closedGroups);
			self::assertSame([$this->fixture->stockPositions[$index]], $closedGroups[0]->getPositions());
			self::assertNull($closedGroups[0]->getTotalAmountInBrokerCurrencyWithDividendsInCzk());
			self::assertSame(
				2000.0 * $factor,
				$this->getService(
					StockClosedPositionFacade::class,
				)->getAllStockClosedPositionsSummaryPrice()->getPriceDifference(),
			);
			$cryptoDto = $crypto->getCryptoAssetDetailDTO($this->fixture->crypto->getId());
			self::assertSame(0.25 * $factor, $cryptoDto->getPiecesCount());
			self::assertSame(50.0 * $factor, $cryptoDto->getCurrentAmount()->getPrice());
			$closedCrypto = $crypto->getCryptoAssetDetailDTO(
				$this->fixture->crypto->getId(),
				CryptoAssetListDetailControlEnum::CLOSED_POSITIONS,
			);
			self::assertSame(0.5 * $factor, $closedCrypto->getPiecesCount());
			self::assertSame(55.0 * $factor, $closedCrypto->getCurrentAmount()->getPrice());
			self::assertSame(
				100.0 * $factor,
				$this->getService(
					CryptoClosedPositionFacade::class,
				)->getAllCryptoClosedPositionsSummaryPrice()->getPriceDifference(),
			);
			self::assertSame(1200.0, $portu->getCurrentPortfolioValueSummaryPrice(CurrencyEnum::CZK)->getPrice());
			self::assertSame(31000.0 * $factor + 1200, $total->getCurrentValue(CurrencyEnum::CZK)->getPrice());
			self::assertSame(20500.0 * $factor + 1100, $total->getTotalInvestedAmount(CurrencyEnum::CZK)->getPrice());
		}
	}

	public function testExplicitOwnerWorksWithoutLoginAndIgnoresAnotherLoggedInOwner(): void
	{
		$total = $this->getService(AssetPriceSummaryFacade::class);
		self::assertSame(32200.0, $total->getCurrentValue(CurrencyEnum::CZK, $this->fixture->admins[0])->getPrice());
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		self::assertSame(32200.0, $total->getCurrentValue(CurrencyEnum::CZK, $this->fixture->admins[0])->getPrice());
		self::assertSame(
			10,
			$this->getService(StockPositionFacade::class)->getStockAssetDetailDTO(
				$this->fixture->stock->getId(),
				appAdmin: $this->fixture->admins[0],
			)->getPiecesCount(),
		);
	}

	public function testDetailsKeepPositionsOrderedByPurchaseDate(): void
	{
		$owner = $this->fixture->admins[0];
		$this->currentAdmin->setApiAppAdmin($owner);
		$em = $this->getService(EntityManagerInterface::class);
		foreach (['2027-01-15', '2025-01-15'] as $date) {
			$now = new ImmutableDateTime($date);
			$em->persist(new StockPosition(
				$owner,
				$this->fixture->stock,
				1,
				100,
				$now,
				new AssetPriceEmbeddable(100, CurrencyEnum::USD),
				false,
				$now,
			));
			$em->persist(new CryptoPosition(
				$owner,
				$this->fixture->crypto,
				1,
				100,
				$now,
				new AssetPriceEmbeddable(100, CurrencyEnum::USD),
				false,
				$now,
			));
		}

		$em->flush();
		$stock = $this->getService(StockPositionFacade::class)->getStockAssetDetailDTO($this->fixture->stock->getId());
		$crypto = $this->getService(CryptoPositionFacade::class)->getCryptoAssetDetailDTO(
			$this->fixture->crypto->getId(),
		);
		$expected = ['2025-01-15', '2026-01-15', '2027-01-15'];
		self::assertSame($expected, array_map(
			static fn (StockAssetPositionDetailDTO $dto): string => $dto->getStockPosition()->getOrderDate()->format(
				'Y-m-d',
			),
			$stock->getPositions(),
		));
		self::assertSame($expected, array_map(
			static fn (CryptoAssetPositionDetailDTO $dto): string => $dto->getCryptoPosition()->getOrderDate()->format(
				'Y-m-d',
			),
			$crypto->getPositions(),
		));
	}

	public function testMissingOwnerDoesNotReturnAllPortfolios(): void
	{
		$this->expectException(AppAdminNotLoggedInException::class);
		$this->getService(AssetPriceSummaryFacade::class)->getCurrentValue(CurrencyEnum::CZK);
	}

	public function testEmptyAccountHasNoPositionsNoProfitAndNoLegacyCharts(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->emptyAdmin);
		$total = $this->getService(AssetPriceSummaryFacade::class);
		self::assertSame(0.0, $total->getCurrentValue(CurrencyEnum::CZK)->getPrice());
		self::assertSame(0.0, $total->getTotalInvestedAmount(CurrencyEnum::CZK)->getPrice());
		self::assertSame(0.0, $total->getTotalPriceDiff(CurrencyEnum::CZK)->getPercentageDifference());
		self::assertSame([], $this->getService(StockClosedPositionFacade::class)->getAllStockClosedPositions());
		self::assertSame([], $this->getService(CryptoClosedPositionFacade::class)->getAllCryptoClosedPositions());
		$dto = $this->getService(StockPositionFacade::class)->getStockAssetDetailDTO($this->fixture->stock->getId());
		self::assertSame([], $dto->getPositions());
		self::assertSame(0.0, $dto->getCurrentPriceDiff()->getPercentageDifference());
		foreach ([
			PortfolioTotalValueChartProvider::class,
			PortfolioTotalValueLastMonthChartProvider::class,
		] as $provider) {
			self::assertSame([], $this->getService($provider)->getChartData()->jsonSerialize()['datasets']);
		}

		foreach ([
			StockDividendByMonthChartDataProvider::class,
			StockDividendByYearChartDataProvider::class,
			StockDividendByCompanyChartDataProvider::class,
			StockDividendByCompanyAndMonthChartDataProvider::class,
		] as $provider) {
			foreach ($this->getService($provider)->getChartData()->jsonSerialize()['datasets'] as $dataset) {
				self::assertSame([], $dataset['data']);
			}
		}

		$chart = $this->getService(TotalInvestedAmountPieChartDataProvider::class)->getChartData()->jsonSerialize();
		self::assertSame([0.0, 0.0], $chart['datasets'][0]['data']);
	}

	public function testDashboardOnlyIncludesOwnersPositionsAndDailyStockTable(): void
	{
		$now = new ImmutableDateTime('2026-01-15');
		$stock = new StockAsset(
			'Only second owner',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'OTHER-' . bin2hex(random_bytes(4)),
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
			null,
		);
		$price = new StockAssetPriceRecord(
			$now,
			CurrencyEnum::USD,
			50,
			$stock,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$now,
		);
		$stock->setCurrentPrice($price, $now);
		$em = $this->getService(EntityManagerInterface::class);
		$em->persist($stock);
		$em->persist($price);
		$em->persist(
			new StockPosition(
				$this->fixture->admins[1],
				$stock,
				1,
				40,
				$now,
				new AssetPriceEmbeddable(40, CurrencyEnum::USD),
				false,
				$now,
			),
		);
		$em->flush();
		$builder = $this->getService(DashboardValueBuilderFacade::class);
		foreach ([0, 1, 0] as $index) {
			$groups = $builder->buildValues(appAdmin: $this->fixture->admins[$index]);
			$byName = [];
			foreach ($groups as $group) {
				$byName[$group->getName()->name] = $group;
			}

			self::assertArrayHasKey(DashboardValueGroupEnum::DIVIDENDS->name, $byName);
			self::assertCount(4, $byName[DashboardValueGroupEnum::TOTAL_VALUES->name]->getPositions());
			self::assertCount($index === 0 ? 0 : 2, $byName[DashboardValueGroupEnum::STOCKS->name]->getTables());
		}
	}

	#[TestWith(['StockAssetDetail', 'detail', 'stock'])]
	#[TestWith(['CryptoAssetDetail', 'detail', 'crypto'])]
	#[TestWith(['StockAssetPositionDetail', 'default', 'stock'])]
	#[TestWith(['StockAssetClosedPositionDetail', 'default', 'stock'])]
	#[TestWith(['CryptoAssetPositionDetail', 'default', 'crypto'])]
	#[TestWith(['CryptoAssetClosedPositionDetail', 'default', 'crypto'])]
	#[TestWith(['Dashboard', 'default', 'stock'])]
	public function testRegularUserCanRenderPersonalPages(string $name, string $action, string $asset): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$presenter = $this->presenter($name);
		$response = $presenter->run(
			new Request(
				'Admin:' . $name,
				'GET',
				['action' => $action, 'id' => $this->fixture->{$asset}->getId()->toString()],
			),
		);
		self::assertInstanceOf(TextResponse::class, $response);
		$template = $response->getSource();
		assert($template instanceof Template);
		$html = $template->renderToString();
		self::assertStringContainsString('<main', $html);
		self::assertStringNotContainsString($this->fixture->stockPositions[0]->getId()->toString(), $html);
		self::assertStringNotContainsString($this->fixture->cryptoPositions[0]->getId()->toString(), $html);
		if ($name === 'StockAssetDetail') {
			self::assertStringNotContainsString("changeTab('aiAnalysis')", $html);
			self::assertStringContainsString("changeTab('dividends')", $html);
		}
	}

	#[TestWith(['StockAssetEdit'])]
	#[TestWith(['CryptoAssetEdit'])]
	public function testCatalogEditingStillRequiresSysadmin(string $name): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$this->expectException(BadRequestException::class);
		$this->presenter($name)->run(new Request('Admin:' . $name, 'GET', ['action' => 'default']));
	}

	#[TestWith(['Dashboard', 'portfolioGoalControl'])]
	#[TestWith(['Dashboard', 'portfolioStatisticTotalControl'])]
	#[TestWith(['StockAssetDetail', 'stockAssetDetailControl-stockAssetAiAnalysisControl'])]
	#[TestWith(['StockAssetDetail', 'stockAssetDetailControl-stockAiValuationComparisonControl'])]
	public function testLegacyWidgetsCannotBeRequestedDirectly(string $name, string $component): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$presenter = $this->presenter($name);
		$presenter->run(
			new Request(
				'Admin:' . $name,
				'GET',
				['action' => $name === 'Dashboard' ? 'default' : 'detail', 'id' => $this->fixture->stock->getId()->toString()],
			),
		);
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);
		$presenter->getComponent($component);
	}

	private function presenter(string $name): BaseAdminPresenter
	{
		$this->replaceService(
			IRequest::class,
			new HttpRequest(new UrlScript('http://localhost/'), headers: ['Sec-Fetch-Site' => 'same-origin']),
		);
		$presenter = $this->getService(IPresenterFactory::class)->createPresenter('Admin:' . $name);
		assert($presenter instanceof BaseAdminPresenter);
		$presenter->autoCanonicalize = false;
		return $presenter;
	}

	/** @param class-string $type */
	private function replaceService(string $type, object $service): void
	{
		foreach ($this->container->findByType($type) as $name) {
			$this->container->removeService($name);
			$this->container->addService($name, $service);
		}
	}

}
