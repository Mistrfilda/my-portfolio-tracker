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
			->method('getData')
			->willReturn([
				'trends' => [
					[
						'name' => 'Test Asset',
						'currentPrice' => 100.0,
						'currency' => 'CZK',
						'trend' => 3.0,
					],
					[
						'name' => 'Second Asset',
						'currentPrice' => 25.5,
						'currency' => 'USD',
						'trend' => -4.5,
					],
					[
						'name' => 'Strong Asset',
						'currentPrice' => 50.0,
						'currency' => 'EUR',
						'trend' => 6.0,
					],
					[
						'name' => 'Portfolio na míru - rizikový profil',
						'currentPrice' => 10726070.0,
						'currency' => 'CZK',
						'trend' => 5.0,
					],
				],
			]);
		$notification
			->expects($this->never())
			->method('getMessage');

		$message = $this->discordMessageService->getMessage($notification);
		self::assertCount(3, $message['embeds']);
		self::assertSame([
			'title' => '📊 Přehled trendů aktiv',
			'description' => 'Časové okno: **7 dní**',
			'color' => 3447003,
			'timestamp' => '2023-01-01T12:00:00.000000Z',
		], $message['embeds'][0]);
		self::assertSame('📈 Růst · 3', $message['embeds'][1]['title']);
		self::assertSame(3066993, $message['embeds'][1]['color']);
		self::assertStringContainsString('Aktivum', $message['embeds'][1]['description']);
		self::assertStringContainsString('Cena', $message['embeds'][1]['description']);
		self::assertStringContainsString('Test Asset', $message['embeds'][1]['description']);
		self::assertStringContainsString('▲ +3.00 %', $message['embeds'][1]['description']);
		self::assertStringContainsString('Portfolio na míru - riz…', $message['embeds'][1]['description']);
		self::assertStringNotContainsString('rizikový profil', $message['embeds'][1]['description']);
		foreach (explode("\n", $message['embeds'][1]['description']) as $line) {
			self::assertLessThanOrEqual(60, mb_strlen($line));
		}

		self::assertLessThan(
			mb_strpos($message['embeds'][1]['description'], 'Test Asset'),
			mb_strpos($message['embeds'][1]['description'], 'Strong Asset'),
		);
		self::assertSame('📉 Pokles · 1', $message['embeds'][2]['title']);
		self::assertSame(15158332, $message['embeds'][2]['color']);
		self::assertStringContainsString('Second Asset', $message['embeds'][2]['description']);
		self::assertStringContainsString('▼ -4.50 %', $message['embeds'][2]['description']);
	}

}
