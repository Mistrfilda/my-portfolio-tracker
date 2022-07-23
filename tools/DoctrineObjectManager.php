<?php

declare(strict_types = 1);

use App\Bootstrap;
use Doctrine\Persistence\ObjectManager;

require __DIR__ . '/../vendor/autoload.php';

$container = Bootstrap::boot()->createContainer();

return $container->getByType(ObjectManager::class);
