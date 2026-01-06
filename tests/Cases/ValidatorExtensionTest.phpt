<?php declare(strict_types = 1);

namespace Tests\Cases;

use Closure;
use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Contributte\Validator\DI\ValidatorExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Definitions\Statement;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @param mixed[] $config
 */
function createContainer(array $config, ?string $key = null): Container
{
	$tempDir = Environment::getTestDir();
	$loader = new ContainerLoader($tempDir, true);

	$containerClass = $loader->load(static function (Compiler $compiler) use ($tempDir, $config): void {
		$compiler->addExtension('validator', new ValidatorExtension());
		$compiler->addConfig(['parameters' => ['tempDir' => $tempDir], 'validator' => $config]);
		$compiler->addConfig(['services' => [new Statement(Translator::class, ['en'])]]);
	}, $key);

	return new $containerClass();
}

function assertValidatorBuilderProperty(
	Container $container,
	string $propertyName,
	Closure $assertion
): void
{
	$validatorBuilder = $container->getService('validator.validatorBuilder');
	$property = new ReflectionProperty($validatorBuilder, $propertyName);
	$value = $property->getValue($validatorBuilder);

	$assertion($value);
}

// testCreateValidator
Toolkit::test(function (): void {
	$container = createContainer([], 'testCreateValidator');

	$validator = $container->getByType(ValidatorInterface::class);
	Assert::type(ValidatorInterface::class, $validator);
});

// testAttributesMapping
Toolkit::test(function (): void {
	$container = createContainer([
		'mapping' => [
			'attributes' => true,
		],
	], 'testAttributesMapping');

	assertValidatorBuilderProperty($container, 'enableAttributeMapping', static function ($value): void {
		Assert::true($value);
	});
});

// testXmlMapping
Toolkit::test(function (): void {
	$container = createContainer([
		'mapping' => [
			'xml' => [
				__DIR__ . '/validator.xml',
			],
		],
	], 'testXmlMapping');

	assertValidatorBuilderProperty($container, 'xmlMappings', static function ($value): void {
		Assert::same([__DIR__ . '/validator.xml'], $value);
	});
});

// testYamlMapping
Toolkit::test(function (): void {
	$container = createContainer([
		'mapping' => [
			'yaml' => [
				__DIR__ . '/validator.yml',
			],
		],
	], 'testYamlMapping');

	assertValidatorBuilderProperty($container, 'yamlMappings', static function ($value): void {
		Assert::same([__DIR__ . '/validator.yml'], $value);
	});
});

// testMethodMapping
Toolkit::test(function (): void {
	$container = createContainer([
		'mapping' => [
			'methods' => [
				'provideConstraints',
			],
		],
	], 'testMethodMapping');

	assertValidatorBuilderProperty($container, 'methodMappings', static function ($value): void {
		Assert::same(['provideConstraints'], $value);
	});
});

// testLoaders
Toolkit::test(function (): void {
	$container = createContainer([
		'loaders' => [
			new Statement(LoaderChain::class, [[]]),
		],
	], 'testLoaders');

	assertValidatorBuilderProperty($container, 'loaders', static function ($value): void {
		Assert::type('list', $value);
		Assert::type(LoaderChain::class, $value[0]);
	});
});

// testDefaultCache
Toolkit::test(function (): void {
	$container = createContainer([], 'testDefaultCache');
	assertValidatorBuilderProperty($container, 'mappingCache', static function ($value): void {
		Assert::type(FilesystemAdapter::class, $value);
	});
});

// testCustomCache
Toolkit::test(function (): void {
	$container = createContainer([
		'cache' => new Statement(NullAdapter::class),
	], 'testCustomCache');
	assertValidatorBuilderProperty($container, 'mappingCache', static function ($value): void {
		Assert::type(NullAdapter::class, $value);
	});
});

// testDisableCache
Toolkit::test(function (): void {
	$container = createContainer(['cache' => null], 'testDisableCache');
	assertValidatorBuilderProperty($container, 'mappingCache', static function ($value): void {
		Assert::type(ArrayAdapter::class, $value);
	});
});

// testNoTranslator
Toolkit::test(function (): void {
	$container = createContainer([
		'translation' => [
			'translator' => false,
		],
	], 'testNoTranslator');

	assertValidatorBuilderProperty($container, 'translator', static function ($value): void {
		Assert::null($value);
	});
});

// testAutowiredTranslator
Toolkit::test(function (): void {
	$container = createContainer([
		'translation' => [
			'domain' => 'validation',
		],
	], 'testAutowiredTranslator');

	assertValidatorBuilderProperty($container, 'translator', static function ($value): void {
		Assert::type(Translator::class, $value);
	});
	assertValidatorBuilderProperty($container, 'translationDomain', static function ($value): void {
		Assert::same('validation', $value);
	});
});

// testSpecificTranslator
Toolkit::test(function (): void {
	$container = createContainer([
		'translation' => [
			'translator' => new Statement(IdentityTranslator::class),
		],
	], 'testSpecificTranslator');

	assertValidatorBuilderProperty($container, 'translator', static function ($value): void {
		Assert::type(IdentityTranslator::class, $value);
	});
});
