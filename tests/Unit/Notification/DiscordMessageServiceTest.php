<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Notification\Discord\DiscordMessageService;
use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DiscordMessageServiceTest extends TestCase
{

	private DiscordMessageService $discordMessageService;

	private DatetimeFactory $datetimeFactory;

	protected function setUp(): void
	{
		$this->datetimeFactory = $this->createMock(DatetimeFactory::class);
		$this->discordMessageService = new DiscordMessageService($this->datetimeFactory);
	}

	public function testGetMessageWithNewDividendType(): void
	{
		$now = new ImmutableDateTime('2023-01-01T12:00:00.000000Z');
		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		$notification = $this->createMock(Notification::class);
		$notification
			->method('getNotificationTypeEnum')
			->willReturn(NotificationTypeEnum::NEW_DIVIDEND);
		$notification
			->method('getMessage')
			->willReturn('New dividend message.');

		$expected = [
			'embeds' => [
				[
					'title' => 'Nová dividenda',
					'description' => 'New dividend message.',
					'color' => 3066993,
					'timestamp' => '2023-01-01T12:00:00.000000Z',
				],
			],
		];

		$this->assertSame($expected, $this->discordMessageService->getMessage($notification));
	}

	public function testGetMessageWithPriceAlertUpType(): void
	{
		$now = new ImmutableDateTime('2023-01-01T12:00:00.000000Z');
		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		$notification = $this->createMock(Notification::class);
		$notification
			->method('getNotificationTypeEnum')
			->willReturn(NotificationTypeEnum::PRICE_ALERT_UP);
		$notification
			->method('getMessage')
			->willReturn('Price alert up message.');

		$expected = [
			'embeds' => [
				[
					'title' => '📈 Price alert up',
					'description' => 'Price alert up message.',
					'color' => 3066993,
					'timestamp' => '2023-01-01T12:00:00.000000Z',
				],
			],
		];

		$this->assertSame($expected, $this->discordMessageService->getMessage($notification));
	}

	public function testGetMessageWithPriceAlertDownType(): void
	{
		$now = new ImmutableDateTime('2023-01-01T12:00:00.000000Z');
		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		$notification = $this->createMock(Notification::class);
		$notification
			->method('getNotificationTypeEnum')
			->willReturn(NotificationTypeEnum::PRICE_ALERT_DOWN);
		$notification
			->method('getMessage')
			->willReturn('Price alert down message.');

		$expected = [
			'embeds' => [
				[
					'title' => '📉 Price alert down',
					'description' => 'Price alert down message.',
					'color' => 15158332,
					'timestamp' => '2023-01-01T12:00:00.000000Z',
				],
			],
		];

		$this->assertSame($expected, $this->discordMessageService->getMessage($notification));
	}

	public function testGetMessageWithAssetTrendsType(): void
	{
		$now = new ImmutableDateTime('2023-01-01T12:00:00.000000Z');
		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		$notification = $this->createMock(Notification::class);
		$notification
			->method('getNotificationTypeEnum')
			->willReturn(NotificationTypeEnum::ASSET_TRENDS);
		$notification
			->expects($this->once())
			->method('getParameter')
			->with(NotificationParameterEnum::TREND_DAYS_THRESHOLD)
			->willReturn(7);
		$notification
			->expects($this->never())
			->method('getMessage');

		self::assertSame([
			'embeds' => [
				[
					'title' => '📊 Přehled trendů aktiv',
					'description' => 'Výrazné cenové pohyby za posledních **7 dní**',
					'color' => 3447003,
					'timestamp' => '2023-01-01T12:00:00.000000Z',
					'image' => ['url' => 'attachment://asset-trends.png'],
				],
			],
			'attachments' => [
				[
					'id' => 0,
					'filename' => 'asset-trends.png',
					'description' => 'Přehled růstu a poklesu cen aktiv za 7 dní',
				],
			],
		], $this->discordMessageService->getMessage($notification));
	}

}
