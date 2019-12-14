<?php declare(strict_types = 1);

namespace Tests\Cases;

use Contributte\Validator\ContainerConstraintValidatorFactory;
use Nette\DI\Compiler;
use Nette\DI\ContainerLoader;
use stdClass;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\DummyConstraint;
use Tests\Fixtures\DummyConstraintValidator;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
final class ContainerConstraintValidatorFactoryTest extends TestCase
{

	public function testGetFromContainer(): void
	{
		$loader = new ContainerLoader(__DIR__ . '/../tmp/nette.configurator', true);
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
	}

	public function testCreateInstanceViaContainer(): void
	{
		$loader = new ContainerLoader(__DIR__ . '/../tmp/nette.configurator', true);
		$containerClass = $loader->load(static function (Compiler $compiler): void {
			$compiler->addConfig([
				'services' => [
					'dependency' => [
						'type' => stdClass::class,
						'imported' => true,
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
	}

}

(new ContainerConstraintValidatorFactoryTest())->run();
