<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Ai\Gemini\GeminiClient;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use App\Stock\AiAnalysis\Codex\StockAiAnalysisCodexBundleFactory;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanFacade;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanRepository;
use App\Stock\AiAnalysis\InvestmentPlan\UI\StockAiInvestmentPlanPresenter;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessProducer;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestionFacade;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestionRepository;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsRepository;
use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\AiAnalysis\UI\StockAiAnalysisPresenter;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Test\Integration\Asset\PositionOwnershipFixture;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Amqp\RabbitMQPublisher;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Application\IPresenterFactory;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\TestWith;
use ZipArchive;

class AiAnalysisOwnershipTest extends IntegrationTestCase
{

	private EntityManagerInterface $em;

	private CurrentAppAdminGetter $currentAdmin;

	private PositionOwnershipFixture $fixture;

	private AppAdmin $emptyOwner;

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$this->fixture = new PositionOwnershipFixture($this->em);
		$now = new ImmutableDateTime('2026-06-01');
		$name = 'empty-ai-' . bin2hex(random_bytes(4));
		$this->emptyOwner = new AppAdmin($name, $name, $name . '@example.test', 'unused', $now, false, false);
		$this->em->persist($this->emptyOwner);
		$price = new StockAssetPriceRecord(
			$now,
			CurrencyEnum::USD,
			150,
			$this->fixture->stock,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$now,
		);
		$this->fixture->stock->setCurrentPrice($price, $now);
		$this->em->persist($price);
		foreach ($this->fixture->admins as $index => $owner) {
			$this->em->persist(new StockPosition(
				$owner,
				$this->fixture->stock,
				($index + 1) * 10,
				($index + 1) * 100,
				$now,
				new AssetPriceEmbeddable(($index + 1) ** 2 * 1000, CurrencyEnum::USD),
				false,
				$now,
			));
			$this->em->persist(
				new StockAssetWatchlist(
					$owner,
					'Private candidate ' . $index,
					'PRIVATE-' . $index,
					100,
					CurrencyEnum::USD,
					$now,
				),
			);
		}

		$this->em->flush();
		$rates = $this->createStub(CurrencyConversionRepository::class);
		$rate = static fn (CurrencyEnum $from, CurrencyEnum $to): CurrencyConversion => new CurrencyConversion(
			$from,
			$to,
			$from === $to ? 1 : 20,
			CurrencySourceEnum::CNB,
			$now,
			$now,
		);
		$rates->method('getCurrentCurrencyPairConversion')->willReturnCallback($rate);
		$rates->method('findCurrencyPairConversionForClosestDate')->willReturnCallback($rate);
		$this->replaceService(CurrencyConversionRepository::class, $rates);
		$producer = $this->createMock(RabbitMQPublisher::class);
		$producer->expects(self::never())->method('publish');
		$this->replaceService(
			StockAiAnalysisGeminiProcessProducer::class,
			new StockAiAnalysisGeminiProcessProducer($producer, 'test-ai'),
		);
		$client = $this->createMock(GeminiClient::class);
		$client->expects(self::never())->method('generateContent');
		$this->replaceService(GeminiClient::class, $client);
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testSnapshotsSettingsAndBundlesFollowOwnerAndStayImmutable(): void
	{
		$facade = $this->getService(StockAiAnalysisFacade::class);
		$settings = $this->getService(StockAiAnalysisSettingsFacade::class);
		$runs = [];
		foreach ([0, 1, 0] as $index) {
			$owner = $this->fixture->admins[$index];
			$this->currentAdmin->setApiAppAdmin($owner);
			$settings->saveInvestorInstructions('Private instructions ' . $index);
			$run = $facade->createRun(true, true, false, StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION);
			$runs[] = $run;
			self::assertSame($owner, $run->getAppAdmin());
			$snapshot = $run->getInputSnapshot();
			self::assertSame('Private instructions ' . $index, $snapshot['investorInstructions']);
			self::assertCount(1, $snapshot['portfolio']);
			self::assertSame(($index + 1) * 100.0, $snapshot['portfolio'][0]['averagePurchasePrice']);
			self::assertSame('PRIVATE-' . $index, $snapshot['simpleWatchlist'][0]['stockAssetTicker']);
			self::assertCount(1, $snapshot['simpleWatchlist']);
			$bundle = $this->getService(StockAiAnalysisCodexBundleFactory::class)->create($run);
			$zip = new ZipArchive();
			try {
				self::assertTrue($zip->open($bundle->filePath));
				$context = $zip->getFromName('instructions/system.md');
				self::assertIsString($context);
				self::assertStringContainsString('Private instructions ' . $index, $context);
				self::assertStringNotContainsString('Private instructions ' . (1 - $index), $context);
				$portfolioJson = $zip->getFromName(
					'input/portfolio-001-' . $this->fixture->stock->getId()->toString() . '.json',
				);
				self::assertIsString($portfolioJson);
				$portfolioItem = Json::decode($portfolioJson, forceArrays: true);
				self::assertSame(($index + 1) * 100.0, $portfolioItem['averagePurchasePrice']);
			} finally {
				$zip->close();
				unlink($bundle->filePath);
			}
		}

		$settings->saveInvestorInstructions('Changed after snapshot');
		self::assertSame('Private instructions 0', $runs[0]->getInputSnapshot()['investorInstructions']);
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		self::assertSame('Private instructions 1', $settings->getInvestorInstructions());
		self::assertSame([$runs[1]], $this->getService(StockAiAnalysisRunRepository::class)->findAll());
		$this->currentAdmin->setApiAppAdmin($this->emptyOwner);
		self::assertSame('', $settings->getInvestorInstructions());
		$empty = $facade->createRun(true, true, false)->getInputSnapshot();
		self::assertSame([], $empty['portfolio']);
		self::assertSame([], $empty['simpleWatchlist']);
	}

	public function testResultsQuestionsPlansAndExportsRejectForeignIds(): void
	{
		$facade = $this->getService(StockAiAnalysisFacade::class);
		$runs = [];
		$plans = [];
		$questions = [];
		foreach ($this->fixture->admins as $index => $owner) {
			$this->currentAdmin->setApiAppAdmin($owner);
			$run = $facade->createRun(true, false, false, StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION);
			$run->setV2Response(
				'{}',
				['schemaVersion' => 2],
				StockAiAnalysisProcessingSourceEnum::MANUAL,
				new ImmutableDateTime(),
			);
			$this->em->flush();
			$runs[] = $run;
			$plans[] = $this->getService(StockAiInvestmentPlanFacade::class)->create(
				1000,
				CurrencyEnum::CZK,
				$run->getId(),
				'My context',
				'',
			);
			self::assertSame(($index + 1) * 30000.0, $plans[$index]->getPortfolioValue()->getPrice());
			$questions[] = $this->getService(StockAiAnalysisFollowUpQuestionFacade::class)->createQuestion(
				$run->getId()->toString(),
				'Private question ' . $index,
			);
			$legacy = new StockAiAnalysisRun(
				$owner,
				'Legacy prompt',
				true,
				false,
				false,
				null,
				new ImmutableDateTime(),
			);
			$this->em->persist($legacy);
			$this->em->flush();
			$facade->processResponse($legacy->getId()->toString(), Json::encode(['portfolioAnalysis' => [[
				'stockAssetId' => $this->fixture->stock->getId()->toString(), 'reasoning' => 'Private result ' . $index,
			]]]));
		}

		foreach ([0, 1, 0] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			$foreign = 1 - $index;
			self::assertSame(
				[$runs[$index]],
				$this->getService(StockAiAnalysisRunRepository::class)->findCompletedPortfolioEvaluations(),
			);
			self::assertSame(
				[$plans[$index]],
				$this->getService(
					StockAiInvestmentPlanRepository::class,
				)->createQueryBuilder()->getQuery()->getResult(),
			);
			$results = $this->getService(StockAiAnalysisStockResultRepository::class)->findLatestForStockAsset(
				$this->fixture->stock,
				1,
			);
			self::assertCount(1, $results);
			self::assertSame('Private result ' . $index, $results[0]->getReasoning());
			$questionFacade = $this->getService(StockAiAnalysisFollowUpQuestionFacade::class);
			self::assertSame([$questions[$index]], $questionFacade->getQuestionsForRun($runs[$index]));
			self::assertSame([], $questionFacade->getQuestionsForRun($runs[$foreign]));
			foreach ([
				static fn () => $facade->getRun($runs[$foreign]->getId()->toString()),
				static fn () => $facade->enqueueGeminiProcessing($runs[$foreign]->getId()->toString()),
				static fn () => $facade->processResponse($runs[$foreign]->getId()->toString(), '{}'),
				static fn () => $questionFacade->getQuestion($questions[$foreign]->getId()->toString()),
				static fn () => $questionFacade->createQuestion($runs[$foreign]->getId()->toString(), 'Foreign'),
				static fn () => $questionFacade->processManualResponse(
					$questions[$foreign]->getId()->toString(),
					'Foreign',
				),
				static fn () => $questionFacade->enqueueGeminiProcessing($questions[$foreign]->getId()->toString()),
				static fn () => $questionFacade->processManualResponse(
					$questions[$index]->getId()->toString(),
					'Wrong parent',
					$runs[$foreign]->getId()->toString(),
				),
				fn () => $this->getService(StockAiInvestmentPlanFacade::class)->create(
					1000,
					CurrencyEnum::CZK,
					$runs[$foreign]->getId(),
					'',
					'',
				),
				fn () => $this->getService(StockAiInvestmentPlanFacade::class)->processCodexResponse(
					$plans[$foreign]->getId()->toString(),
					'{}',
				),
			] as $attempt) {
				$this->assertNotFound($attempt);
			}

			$factory = $this->getService(IPresenterFactory::class);
			$analysisPresenter = $factory->createPresenter('Admin:StockAiAnalysis');
			assert($analysisPresenter instanceof StockAiAnalysisPresenter);
			$planPresenter = $factory->createPresenter('Admin:StockAiInvestmentPlan');
			assert($planPresenter instanceof StockAiInvestmentPlanPresenter);
			foreach (['actionDetail', 'actionDownloadPdf', 'actionDownloadCodexBundle'] as $action) {
				$this->assertNotFound(
					static fn () => $analysisPresenter->$action($runs[$foreign]->getId()->toString()),
				);
				$this->assertNotFound(static fn () => $planPresenter->$action($plans[$foreign]->getId()->toString()));
			}
		}
	}

	public function testBackgroundProcessingDerivesOwnerAndUsesImmutableSnapshot(): void
	{
		$runs = [];
		foreach ($this->fixture->admins as $index => $owner) {
			$this->currentAdmin->setApiAppAdmin($owner);
			$this->getService(StockAiAnalysisSettingsFacade::class)->saveInvestorInstructions(
				'Private worker ' . $index,
			);
			$runs[] = $this->getService(StockAiAnalysisFacade::class)->createRun(false, false, false);
		}

		$client = $this->createMock(GeminiClient::class);
		$call = 0;
		$client->expects(self::exactly(2))->method('generateContent')->willReturnCallback(
			static function (string $prompt, string $instruction) use ($runs, &$call): string {
				self::assertStringContainsString('Private worker ' . $call, $instruction);
				self::assertStringNotContainsString('Private worker ' . (1 - $call), $instruction);
				$run = $runs[$call++];
				return Json::encode([
					'schemaVersion' => 2, 'runId' => $run->getId()->toString(),
					'analysisAsOf' => $run->getInputSnapshot()['analysisAsOf'],
				]);
			},
		);
		$this->replaceService(GeminiClient::class, $client);
		$processor = $this->getService(StockAiAnalysisGeminiProcessorFacade::class);
		// A is processed while B is current; B is then processed with no session.
		$processor->process($runs[0]->getId()->toString());
		$this->currentAdmin->beginApiRequest();
		$processor->process($runs[1]->getId()->toString());
		$processor->process($runs[0]->getId()->toString());
		foreach ($runs as $index => $run) {
			self::assertNotNull($run->getProcessedAt());
			self::assertSame($this->fixture->admins[$index], $run->getAppAdmin());
			self::assertSame(StockAiAnalysisProcessingSourceEnum::GEMINI, $run->getProcessingSource());
		}
	}

	/** @param class-string<BaseRepository<object>> $repository */
	#[TestWith([StockAiAnalysisRunRepository::class])]
	#[TestWith([StockAiAnalysisSettingsRepository::class])]
	#[TestWith([StockAiAnalysisStockResultRepository::class])]
	#[TestWith([StockAiAnalysisFollowUpQuestionRepository::class])]
	#[TestWith([StockAiInvestmentPlanRepository::class])]
	public function testMissingOwnerNeverReadsAllAiData(string $repository): void
	{
		$this->expectException(AppAdminNotLoggedInException::class);
		$this->getService($repository)->createQueryBuilder();
	}

	private function assertNotFound(callable $attempt): void
	{
		try {
			$attempt();
		} catch (NoEntityFoundException) {
			self::assertTrue(true);
			return;
		}

		self::fail('Foreign AI data must be inaccessible.');
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
