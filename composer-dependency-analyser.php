<?php

declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$classNameRegex = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';
$dicFileContents = file_get_contents(__DIR__ . '/config/config.neon');

preg_match_all(
	"~$classNameRegex(?:\\\\$classNameRegex)+~",
	$dicFileContents,
	$matches
);


$config = new Configuration();

return $config
	->addForceUsedSymbols($matches[0])
	->ignoreErrorsOnPackage('gedmo/doctrine-extensions', [ErrorType::UNUSED_DEPENDENCY])
	->ignoreErrorsOnPackage('nette/finder', [ErrorType::UNUSED_DEPENDENCY])
	->ignoreErrorsOnPackage('nette/mail', [ErrorType::UNUSED_DEPENDENCY])
	->ignoreErrorsOnPackage('nette/robot-loader', [ErrorType::UNUSED_DEPENDENCY])
	->ignoreErrorsOnPackage('nette/caching', [ErrorType::UNUSED_DEPENDENCY])
	->ignoreErrorsOnPackage('nettrine/annotations', [ErrorType::UNUSED_DEPENDENCY]);
