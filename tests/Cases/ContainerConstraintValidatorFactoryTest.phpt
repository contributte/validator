<?php declare(strict_types = 1);

namespace Tests\Cases;

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Contributte\Validator\ContainerConstraintValidatorFactory;
use Nette\DI\Compiler;
use Nette\DI\ContainerLoader;
use stdClass;
use Tester\Assert;
use Tests\Fixtures\DummyConstraint;
use Tests\Fixtures\DummyConstraintValidator;

require __DIR__ . '/../bootstrap.php';

Toolkit::test(function (): void {
	$loader = new ContainerLoader(Environment::getTestDir(), true);
	$containerClass = $loader->load(static function (Compiler $compiler): void {
		$compiler->addConfig([
			'services' => [
				'dependency' => [
					'type' => stdClass::class,
					'imported' => true,
				],
				[
					'factory' => DummyConstraintValidator::class,
				],
			],
		]);
	});

	$container = new $containerClass();

	$expectedDependency = new stdClass();
	$container->addService('dependency', $expectedDependency);

	$factory = new ContainerConstraintValidatorFactory($container);
	$constraintValidator = $factory->getInstance(new DummyConstraint());
	Assert::type(DummyConstraintValidator::class, $constraintValidator);
	Assert::same($expectedDependency, $constraintValidator->dependency);
});

Toolkit::test(function (): void {
	$loader = new ContainerLoader(Environment::getTestDir(), true);
	$containerClass = $loader->load(static function (Compiler $compiler): void {
		$compiler->addConfig([
			'services' => [
				'dependency' => [
					'type' => stdClass::class,
					'imported' => true,
				],
			],
		]);
	}, 'createInstance');

	$container = new $containerClass();

	$expectedDependency = new stdClass();
	$container->addService('dependency', $expectedDependency);

	$factory = new ContainerConstraintValidatorFactory($container);
	$constraintValidator = $factory->getInstance(new DummyConstraint());
	Assert::type(DummyConstraintValidator::class, $constraintValidator);
	Assert::same($expectedDependency, $constraintValidator->dependency);
});
