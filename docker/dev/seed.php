<?php

declare(strict_types = 1);

use App\Admin\AppAdminFacade;
use App\Admin\AppAdminRepository;
use App\Bootstrap;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

require __DIR__ . '/../../vendor/autoload.php';

if (getenv('MY_PORTFOLIO_TRACKER_DOCKER_DEV') !== '1') {
	throw new RuntimeException('The development account requires Docker development mode.');
}

$container = Bootstrap::boot()->createContainer();
if ($container->getByType(AppAdminRepository::class)->getCount() === 0) {
	$container->getByType(AppAdminFacade::class)->createAppAdmin(
		'Developer',
		'developer',
		'developer@example.test',
		TypeValidator::validateString(getenv('MPT_DEV_ADMIN_PASSWORD')),
		false,
		true,
	);
}

$entityManager = $container->getByType(EntityManagerInterface::class);
if ($entityManager->getRepository(CurrencyConversion::class)->count([]) === 0) {
	// CNB fixing on 2025-01-02, in CZK per unit. Cross rates use the same fixing.
	$forDate = new ImmutableDateTime('2025-01-02');
	$czkPerUnit = ['CZK' => 1.0, 'EUR' => 25.175, 'USD' => 24.398, 'GBP' => 30.271, 'PLN' => 5.888, 'NOK' => 2.149];
	foreach ($czkPerUnit as $from => $fromRate) {
		foreach ($czkPerUnit as $to => $toRate) {
			if ($from !== $to) {
				$entityManager->persist(new CurrencyConversion(
					CurrencyEnum::from($from),
					CurrencyEnum::from($to),
					$fromRate / $toRate,
					CurrencySourceEnum::CNB,
					$forDate,
					$forDate,
				));
			}
		}
	}
	$entityManager->flush();
}
