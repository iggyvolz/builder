<?php
declare(strict_types=1);

use iggyvolz\builder\BuilderGenerator;
use iggyvolz\builder\example\DemoPodo;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . "/../vendor/autoload.php";
Environment::setup();
BuilderGenerator::register();
$builder = DemoPodo::builder();
Assert::throws($builder->build(...), RuntimeException::class, "Did not include required property foo");