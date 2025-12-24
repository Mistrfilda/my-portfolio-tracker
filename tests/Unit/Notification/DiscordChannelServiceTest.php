<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Notification\Discord\DiscordChannelEnum;
use App\Notification\Discord\DiscordChannelService;
use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationTypeEnum;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DiscordChannelServiceTest extends TestCase
{

	public function testWebhookUrlRetrievedFromDirectMapping(): void
	{
		$mapping = [
			NotificationTypeEnum::NEW_DIVIDEND->value => 'https://example.com/webhook/new-dividend',
		];
		$notification = $this->createNotification(NotificationTypeEnum::NEW_DIVIDEND);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertSame('https://example.com/webhook/new-dividend', $result);
	}

	public function testWebhookUrlPriceAlertWithValidThreshold(): void
	{
		$mapping = [
			DiscordChannelEnum::TREND_ALERT_7_DAYS->value => 'https://example.com/webhook/trend-alert-7-days',
		];
		$notification = $this->createNotification(
			NotificationTypeEnum::PRICE_ALERT_UP,
			[NotificationParameterEnum::TREND_DAYS_THRESHOLD->value => 7],
		);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertSame('https://example.com/webhook/trend-alert-7-days', $result);
	}

	public function testWebhookUrlPriceAlertWithDefaultThreshold(): void
	{
		$mapping = [
			DiscordChannelEnum::TREND_ALERT_DEFAULT->value => 'https://example.com/webhook/trend-alert-default',
		];
		$notification = $this->createNotification(
			NotificationTypeEnum::PRICE_ALERT_DOWN,
			[NotificationParameterEnum::TREND_DAYS_THRESHOLD->value => 30],
		);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertSame('https://example.com/webhook/trend-alert-default', $result);
	}

	public function testWebhookUrlPriceAlertWithMissingThreshold(): void
	{
		$mapping = [
			DiscordChannelEnum::TREND_ALERT_DEFAULT->value => 'https://example.com/webhook/trend-alert-default',
		];
		$notification = $this->createNotification(NotificationTypeEnum::PRICE_ALERT_UP);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertNull($result);
	}

	public function testWebhookUrlMissingMapping(): void
	{
		$mapping = [];
		$notification = $this->createNotification(NotificationTypeEnum::NEW_DIVIDEND);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertNull($result);
	}

	/**
	 * @param array<string, int|string> $parameters
	 */
	private function createNotification(NotificationTypeEnum $typeEnum, array $parameters = []): Notification
	{
		$notification = $this->createMock(Notification::class);
		$notification->method('getNotificationTypeEnum')->willReturn($typeEnum);
		$notification->method('getParameter')->willReturnCallback(
			static fn (NotificationParameterEnum $key) => $parameters[$key->value] ?? null,
		);
		return $notification;
	}

}
