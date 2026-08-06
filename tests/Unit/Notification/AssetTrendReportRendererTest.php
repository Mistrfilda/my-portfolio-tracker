<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Notification\Discord\AssetTrendReportRenderer;
use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use PHPUnit\Framework\TestCase;

class AssetTrendReportRendererTest extends TestCase
{

	public function testRenderCreatesEscapedAndSortedTrendReport(): void
	{
		$notification = $this->createMock(Notification::class);
		$notification
			->expects($this->once())
			->method('getParameter')
			->with(NotificationParameterEnum::TREND_DAYS_THRESHOLD)
			->willReturn(7);
		$notification
			->expects($this->once())
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
						'name' => '<Unsafe Asset>',
						'currentPrice' => 25.5,
						'currency' => 'USD',
						'trend' => -4.5,
					],
					[
						'name' => 'Portfolio na míru - rizikový profil',
						'currentPrice' => 10726070.0,
						'currency' => 'CZK',
						'trend' => 5.0,
					],
				],
			]);

		$html = new AssetTrendReportRenderer()->render($notification);

		self::assertStringContainsString('<html lang="cs">', $html);
		self::assertStringContainsString('<strong>7</strong>', $html);
		self::assertStringContainsString('<span>dní</span>', $html);
		self::assertStringContainsString('Portfolio na míru - rizikový profil', $html);
		self::assertStringContainsString('10 726 070.00 CZK', $html);
		self::assertStringContainsString('▲ +5.00 %', $html);
		self::assertStringContainsString('▼ -4.50 %', $html);
		self::assertStringContainsString('&lt;Unsafe Asset&gt;', $html);
		self::assertStringNotContainsString('<Unsafe Asset>', $html);
		self::assertLessThan(
			mb_strpos($html, 'Test Asset'),
			mb_strpos($html, 'Portfolio na míru - rizikový profil'),
		);
	}

}
