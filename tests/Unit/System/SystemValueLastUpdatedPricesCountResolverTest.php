<?php

declare(strict_types = 1);

namespace App\Test\Unit\System;

use App\Stock\Asset\StockAssetRepository;
use App\System\Resolver\SystemValueLastUpdatedPricesCountResolver;
use App\System\SystemValueEnum;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class SystemValueLastUpdatedPricesCountResolverTest extends UpdatedTestCase
{

	private SystemValueLastUpdatedPricesCountResolver $resolver;

	private StockAssetRepository $stockAssetRepository;

	private DatetimeFactory $datetimeFactory;

	protected function setUp(): void
	{
		$this->stockAssetRepository = Mockery::mock(StockAssetRepository::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);

		$this->resolver = new SystemValueLastUpdatedPricesCountResolver(
			$this->stockAssetRepository,
			$this->datetimeFactory,
		);
	}

	public function testBeforeFirstUpdateToday(): void
	{
		// Úterý 10:00 - před první aktualizací (12:25)
		$now = new ImmutableDateTime('2024-01-16 10:00:00'); // Úterý

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-16 00:00:00'));

		// Mělo by hledat od včerejší 22:25
		$expectedDateTime = '2024-01-15 22:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testAfterFirstUpdateBeforeSecond(): void
	{
		// Úterý 14:00 - po první (12:25), před druhou (16:25)
		$now = new ImmutableDateTime('2024-01-16 14:00:00');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-16 00:00:00'));

		// Mělo by hledat od dnešní 12:25
		$expectedDateTime = '2024-01-16 12:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testAfterSecondUpdateBeforeThird(): void
	{
		// Úterý 18:00 - po druhé (16:25), před třetí (22:25)
		$now = new ImmutableDateTime('2024-01-16 18:00:00');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-16 00:00:00'));

		// Mělo by hledat od dnešní 16:25
		$expectedDateTime = '2024-01-16 16:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testAfterThirdUpdate(): void
	{
		// Úterý 23:00 - po třetí aktualizaci (22:25)
		$now = new ImmutableDateTime('2024-01-16 23:00:00');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-16 00:00:00'));

		// Mělo by hledat od dnešní 22:25
		$expectedDateTime = '2024-01-16 22:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testSaturdayAfternoon(): void
	{
		// Sobota 15:00 - mělo by hledat od pátku 22:25
		$now = new ImmutableDateTime('2024-01-20 15:00:00'); // Sobota

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-20 00:00:00'));

		// Mělo by hledat od pátku 22:25
		$expectedDateTime = '2024-01-19 22:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testSundayAfternoon(): void
	{
		// Neděle 17:00 - mělo by hledat od pátku 22:25
		$now = new ImmutableDateTime('2024-01-21 17:00:00'); // Neděle

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-21 00:00:00'));

		// Mělo by hledat od pátku 22:25
		$expectedDateTime = '2024-01-19 22:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testMondayBeforeFirstUpdate(): void
	{
		// Pondělí 10:00 - před první aktualizací, mělo by hledat od pátku 22:25
		$now = new ImmutableDateTime('2024-01-22 10:00:00'); // Pondělí

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-22 00:00:00'));

		// Mělo by hledat od pátku 22:25
		$expectedDateTime = '2024-01-19 22:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testExactlyAtFirstUpdateTime(): void
	{
		// Úterý přesně 12:25 - mělo by vrátit dnešní 12:25
		$now = new ImmutableDateTime('2024-01-16 12:25:00');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-16 00:00:00'));

		$expectedDateTime = '2024-01-16 12:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150);

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

	public function testManualUpdateAfterScheduled(): void
	{
		// Úterý 17:30 - manuální update po automatické aktualizaci v 16:25
		// Repository by mělo počítat všechny akcie >= 16:25, včetně těch z 17:30
		$now = new ImmutableDateTime('2024-01-16 17:30:00');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn(new ImmutableDateTime('2024-01-16 00:00:00'));

		$expectedDateTime = '2024-01-16 16:25:00';

		$this->stockAssetRepository
			->shouldReceive('getCountUpdatedPricesSince')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $dt) => $dt->format('Y-m-d H:i:s') === $expectedDateTime))
			->andReturn(150); // Mělo by počítat i manuální aktualizace

		$result = $this->resolver->getValueForEnum(SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT);

		$this->assertSame(150, $result);
	}

}
