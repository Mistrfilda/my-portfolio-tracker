<?php

declare(strict_types = 1);

namespace App\Test\Integration\PersonalData;

use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Bank\Account\BankAccountFacade;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Bank\Kb\KbCashFacade;
use App\Cash\Bank\Kb\KbSourceEnum;
use App\Cash\Expense\Bank\BankExpenseFormFacade;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\Cash\Expense\UI\BankExpenseFormFactory;
use App\Cash\Expense\UI\BankExpenseUploadFormFactory;
use App\Cash\Expense\UI\ExpensePresenter;
use App\Cash\Income\Bank\BankIncomeRepository;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Home\Device\HomeDeviceFacade;
use App\Home\Device\HomeDeviceRepository;
use App\Home\Device\HomeDeviceType;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\Home\Device\UI\HomeDeviceFormFactory;
use App\Home\Home;
use App\Home\HomeFacade;
use App\Home\HomeRepository;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Test\Integration\IntegrationTestCase;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Base\BasePresenter;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Bootstrap\Configurator;
use Nette\Forms\Controls\SelectBox;
use Nette\Http\IRequest;
use Nette\Http\Request as HttpRequest;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionProperty;

class PersonalDataOwnershipTest extends IntegrationTestCase
{

	private PersonalDataOwnershipFixture $fixture;

	private CurrentAppAdminGetter $currentAdmin;

	protected function configureContainer(Configurator $configurator): void
	{
		$configurator->addConfig([
			'session' => ['autoStart' => 'never'],
			'parameters' => ['workMonthlyIncome' => ['harvestAccessToken' => 'unused-test-token']],
		]);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixture = new PersonalDataOwnershipFixture($this->getService(EntityManagerInterface::class));
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$this->replaceJobFacade($this->createStub(JobRequestFacade::class));
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testOwnerQueriesIdsAndEmptyAccountAcrossAllPersonalModules(): void
	{
		$repositories = [
			BankAccountRepository::class => $this->fixture->accounts,
			BankExpenseRepository::class => $this->fixture->expenses,
			BankIncomeRepository::class => $this->fixture->incomes,
			WorkMonthlyIncomeRepository::class => $this->fixture->workIncomes,
			HomeRepository::class => $this->fixture->homes,
			HomeDeviceRepository::class => $this->fixture->devices,
			HomeDeviceRecordRepository::class => $this->fixture->records,
		];
		foreach ([0, 1, 0, 2] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			foreach ($repositories as $class => $records) {
				$repository = $this->getService($class);
				$expected = $index === 2 ? [] : [$records[$index]];
				self::assertSame(
					$expected,
					$repository->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult(),
				);
				if ($index !== 2) {
					self::assertSame($records[$index], $repository->getById($records[$index]->getId()));
					$this->assertNotFound(static fn () => $repository->getById($records[1 - $index]->getId()));
				}
			}

			$tags = $this->getService(ExpenseTagRepository::class);
			self::assertSame($index === 2 ? [] : [$this->fixture->tags[$index]], $tags->findAllMain());
			self::assertSame($index === 2 ? [] : [$this->fixture->childTags[$index]], $tags->findAllOtherTags());
		}
	}

	public function testBulkLookupsAndFormOptionsExcludeForeignRecords(): void
	{
		foreach ([
			BankExpenseRepository::class => $this->fixture->expenses,
			BankIncomeRepository::class => $this->fixture->incomes,
			WorkMonthlyIncomeRepository::class => $this->fixture->workIncomes,
			ExpenseTagRepository::class => $this->fixture->tags,
		] as $class => $records) {
			self::assertSame(
				[$records[0]],
				$this->getService($class)->findByIds([$records[0]->getId(), $records[1]->getId()]),
			);
		}

		self::assertSame(
			[$this->fixture->accounts[0]->getId()->toString()],
			array_keys($this->getService(BankAccountRepository::class)->findPairs()),
		);
		$forms = [
			$this->getService(BankExpenseFormFactory::class)->create(static function (): void {
			}, null, null),
			$this->getService(BankExpenseUploadFormFactory::class)->create(
				BankSourceEnum::KOMERCNI_BANKA,
				static function (): void {
				},
			),
		];
		foreach ($forms as $form) {
			$select = $form['bankAccount'];
			assert($select instanceof SelectBox);
			self::assertSame([$this->fixture->accounts[0]->getId()->toString()], array_keys($select->getItems()));
		}

		self::assertNotContains(
			$this->fixture->tags[1]->getId(),
			array_keys($this->getService(ExpenseTagRepository::class)->findPairs()),
		);
	}

	public function testForeignAccountsTagsAndDevicesCannotBeAssignedOrChanged(): void
	{
		$f = $this->fixture;
		$expenseFacade = $this->getService(BankExpenseFormFacade::class);
		$tags = $this->getService(ExpenseTagFacade::class);
		$devices = $this->getService(HomeDeviceFacade::class);
		foreach ([
			fn () => $this->getService(BankAccountFacade::class)->update(
				$f->accounts[1]->getId(),
				'Changed',
				'Bank',
				BankAccountTypeEnum::BUSINESS,
			),
			static fn () => $expenseFacade->create(
				null,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::CARD_PAYMENT,
				5.0,
				CurrencyEnum::CZK,
				null,
				null,
				'',
				$f->accounts[1]->getId(),
			),
			static fn () => $expenseFacade->update(
				$f->expenses[0]->getId(),
				null,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::CARD_PAYMENT,
				5.0,
				CurrencyEnum::CZK,
				null,
				null,
				'',
				$f->accounts[1]->getId(),
			),
			static fn () => $expenseFacade->update(
				$f->expenses[1]->getId(),
				null,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::CARD_PAYMENT,
				5.0,
				CurrencyEnum::CZK,
				null,
				null,
				'',
				$f->accounts[0]->getId(),
			),
			static fn () => $tags->create('Child', null, $f->tags[1]->getId(), [], false),
			static fn () => $tags->update($f->tags[1]->getId(), 'Changed', [], false),
			static fn () => $tags->manuallySetMainTag($f->expenses[0]->getId(), $f->tags[1]->getId()),
			static fn () => $tags->manuallySetMainTag($f->expenses[1]->getId(), $f->tags[0]->getId()),
			static fn () => $tags->manuallySetOtherTag($f->expenses[0]->getId(), $f->childTags[1]->getId()),
			static fn () => $tags->manuallyRemoveOtherTag($f->expenses[0]->getId(), $f->childTags[1]->getId()),
			fn () => $this->getService(KbCashFacade::class)->processFileContents(
				'',
				KbSourceEnum::CSV,
				$f->accounts[1],
			),
			fn () => $this->getService(HomeFacade::class)->update($f->homes[1]->getId(), 'Changed'),
			static fn () => $devices->create(
				$f->homes[1]->getId(),
				'invalid-device',
				'Changed',
				HomeDeviceType::SENSOR,
			),
			static fn () => $devices->update($f->devices[1]->getId(), 'changed', 'Changed', HomeDeviceType::SENSOR),
			fn () => $this->getService(HomeDeviceFormFactory::class)->create(
				$f->homes[1],
				null,
				static function (): void {
				},
			),
			fn () => $this->getService(HomeDeviceFormFactory::class)->create(
				$f->homes[0],
				$f->devices[1]->getId()->toString(),
				static function (): void {
				},
			),
		] as $operation) {
			$this->assertNotFound($operation);
		}

		self::assertSame($f->accounts[0], $f->expenses[0]->getBankAccount());
		self::assertNull($f->expenses[0]->getMainTag());
		self::assertSame([], $f->expenses[0]->getOtherTags());
		self::assertSame('Device 1', $f->devices[1]->getName());
	}

	public function testOwnerIsPreservedByCreationAndEdits(): void
	{
		$this->getService(BankAccountFacade::class)->create('Created', 'Bank', BankAccountTypeEnum::PERSONAL);
		$accounts = $this->getService(BankAccountRepository::class)->findAll();
		self::assertCount(2, $accounts);
		foreach ($accounts as $account) {
			$this->getService(BankAccountFacade::class)->update(
				$account->getId(),
				'Updated',
				'Bank',
				BankAccountTypeEnum::BUSINESS,
			);
			self::assertSame($this->fixture->admins[0], $account->getAppAdmin());
		}

		$home = $this->getService(HomeFacade::class)->create('New home');
		$this->getService(HomeFacade::class)->update($home->getId(), 'Updated home');
		$tag = $this->getService(ExpenseTagFacade::class)->create(
			'New child',
			null,
			$this->fixture->tags[0]->getId(),
			[],
			false,
		);
		$this->getService(ExpenseTagFacade::class)->update($tag->getId(), 'Updated child', [], true);
		self::assertSame($this->fixture->admins[0], $home->getAppAdmin());
		self::assertSame($this->fixture->admins[0], $tag->getAppAdmin());
		$this->getService(EntityManagerInterface::class)->clear();
		self::assertTrue(
			$this->fixture->admins[0]->getId()->equals(
				$this->getService(HomeRepository::class)->getById($home->getId())->getAppAdmin()->getId(),
			),
		);
	}

	public function testTagProcessingUsesOneExplicitOwnerAndWorksInEitherOrder(): void
	{
		$this->currentAdmin->beginApiRequest();
		$facade = $this->getService(ExpenseTagFacade::class);
		$facade->processExpensesForAppAdmin($this->fixture->admins[0]);
		self::assertSame($this->fixture->tags[0], $this->fixture->expenses[0]->getMainTag());
		self::assertNull($this->fixture->expenses[1]->getMainTag());
		foreach ([1, 0, 1, 2] as $index) {
			$facade->processExpensesForAppAdmin($this->fixture->admins[$index]);
		}

		foreach ([0, 1] as $index) {
			self::assertSame($this->fixture->tags[$index], $this->fixture->expenses[$index]->getMainTag());
			self::assertSame([$this->fixture->childTags[$index]], $this->fixture->expenses[$index]->getOtherTags());
		}
	}

	public function testDeviceHistoryIsScopedThroughHomeAndRejectsWrongHomeOfSameOwner(): void
	{
		$records = $this->getService(HomeDeviceRecordRepository::class);
		self::assertSame(
			[$this->fixture->records[0]],
			$records->findForDeviceSince($this->fixture->devices[0], $this->createDatetime('2020-01-01')),
		);
		self::assertSame(
			[],
			$records->findForDeviceSince($this->fixture->devices[1], $this->createDatetime('2020-01-01')),
		);
		self::assertSame(
			[],
			$this->getService(HomeDeviceRepository::class)->createQueryBuilderForHome(
				$this->fixture->homes[1],
			)->getQuery()->getResult(),
		);
		$otherHome = new Home($this->fixture->admins[0], 'Another own home', $this->createDatetime());
		$this->getService(EntityManagerInterface::class)->persist($otherHome);
		$this->getService(EntityManagerInterface::class)->flush();
		$this->assertNotFound(
			fn () => $this->getService(
				HomeDeviceFormFactory::class,
			)->create(
				$otherHome,
				$this->fixture->devices[0]->getId()->toString(),
				static function (): void {
				},
			),
		);
	}

	#[TestWith([BankAccountRepository::class])]
	#[TestWith([BankExpenseRepository::class])]
	#[TestWith([BankIncomeRepository::class])]
	#[TestWith([ExpenseTagRepository::class])]
	#[TestWith([WorkMonthlyIncomeRepository::class])]
	#[TestWith([HomeRepository::class])]
	#[TestWith([HomeDeviceRepository::class])]
	#[TestWith([HomeDeviceRecordRepository::class])]
	public function testMissingLoginCannotDisableOwnerFilter(string $class): void
	{
		$this->currentAdmin->beginApiRequest();
		$this->expectException(AppAdminNotLoggedInException::class);
		$this->getService($class)->createQueryBuilderForCurrentAppAdmin();
	}

	#[TestWith(['BankAccount', 'edit', 'accounts'])]
	#[TestWith(['Expense', 'form', 'expenses'])]
	#[TestWith(['ExpenseTag', 'editTag', 'tags'])]
	#[TestWith(['HomeEdit', 'default', 'homes'])]
	#[TestWith(['HomeDeviceEdit', 'default', 'devices'])]
	#[TestWith(['HomeDeviceRecord', 'default', 'devices'])]
	public function testForeignUrlsAreNotFound(string $name, string $action, string $records): void
	{
		$id = $this->fixture->$records[1]->getId();
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);
		$this->presenter($name)->run(new Request('Admin:' . $name, 'GET', [
			'action' => $action, 'id' => (string) $id, 'homeId' => $this->fixture->homes[0]->getId()->toString(),
		]));
	}

	#[TestWith(['BankAccount', 'default'])]
	#[TestWith(['Expense', 'kb'])]
	#[TestWith(['ExpenseTag', 'tags'])]
	#[TestWith(['Home', 'default'])]
	#[TestWith(['HomeDevice', 'default'])]
	#[TestWith(['HomeDeviceRecord', 'default'])]
	#[TestWith(['WorkMonthlyIncome', 'default'])]
	public function testRegularOwnerCanOpenPersonalPages(string $name, string $action): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$response = $this->presenter($name)->run(new Request('Admin:' . $name, 'GET', [
			'action' => $action, 'id' => $this->fixture->devices[1]->getId()->toString(),
			'homeId' => $this->fixture->homes[1]->getId()->toString(),
		]));
		self::assertInstanceOf(TextResponse::class, $response);
	}

	public function testTagJobContainsRequestingOwner(): void
	{
		$job = $this->createMock(JobRequestFacade::class);
		$job->expects(self::once())->method('addToQueue')->with(JobRequestTypeEnum::EXPENSE_TAG_PROCESS, [
			'appAdminId' => $this->fixture->admins[0]->getId()->toString(),
		]);
		$presenter = $this->createPartialMock(ExpensePresenter::class, ['flashMessage', 'redirect']);
		new ReflectionProperty(ExpensePresenter::class, 'jobRequestFacade')->setValue($presenter, $job);
		new ReflectionProperty(BasePresenter::class, 'currentAppAdminGetter')->setValue(
			$presenter,
			$this->currentAdmin,
		);
		$presenter->expects(self::once())->method('redirect')->willThrowException(new AbortException());
		$this->expectException(AbortException::class);
		$presenter->handleAddProcessRequest();
	}

	private function replaceJobFacade(JobRequestFacade $facade): void
	{
		foreach ($this->container->findByType(JobRequestFacade::class) as $name) {
			$this->container->removeService($name);
			$this->container->addService($name, $facade);
		}
	}

	private function presenter(string $name, string $method = 'GET'): BaseAdminPresenter
	{
		foreach ($this->container->findByType(IRequest::class) as $serviceName) {
			$this->container->removeService($serviceName);
			$this->container->addService(
				$serviceName,
				new HttpRequest(
					new UrlScript('http://localhost/'),
					headers: ['Sec-Fetch-Site' => 'same-origin'],
					method: $method,
				),
			);
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
