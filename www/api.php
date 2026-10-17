<?php

declare(strict_types = 1);

use App\Api\SlimAppFactory;
use App\Bootstrap;

require __DIR__ . '/../vendor/autoload.php';

$container = Bootstrap::boot(apiMode: true)
	->createContainer();

/** @var SlimAppFactory $slimAppFactory */
$slimAppFactory = $container->getByType(SlimAppFactory::class);

$app = $slimAppFactory->create();
$app->run();
