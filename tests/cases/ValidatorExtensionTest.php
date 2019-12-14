<?php declare(strict_types = 1);

namespace Tests\Cases;

use Closure;
use Contributte\Validator\DI\ValidatorExtension;
use Doctrine\Common\Annotations\Reader;
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
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
final class ValidatorExtensionTest extends TestCase
{

	public function testCreateValidator(): void
	{
		$container = $this->createContainer([], __METHOD__);

		$validator = $container->getByType(ValidatorInterface::class);
		Assert::type(ValidatorInterface::class, $validator);
	}

	public function testAnnotationMapping(): void
	{
		$container = $this->createContainer([
			'mapping' => [
				'annotations' => true,
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'annotationReader', static function ($value): void {
			Assert::type(Reader::class, $value);
		});
	}

	public function testXmlMapping(): void
	{
		$container = $this->createContainer([
			'mapping' => [
				'xml' => [
					__DIR__ . '/validator.xml',
				],
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'xmlMappings', static function ($value): void {
			Assert::same([__DIR__ . '/validator.xml'], $value);
		});
	}

	public function testYamlMapping(): void
	{
		$container = $this->createContainer([
			'mapping' => [
				'yaml' => [
					__DIR__ . '/validator.yml',
				],
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'yamlMappings', static function ($value): void {
			Assert::same([__DIR__ . '/validator.yml'], $value);
		});
	}

	public function testMethodMapping(): void
	{
		$container = $this->createContainer([
			'mapping' => [
				'methods' => [
					'provideConstraints',
				],
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'methodMappings', static function ($value): void {
			Assert::same(['provideConstraints'], $value);
		});
	}

	public function testLoaders(): void
	{
		$container = $this->createContainer([
			'loaders' => [
				new Statement(LoaderChain::class, [[]]),
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'loaders', static function ($value): void {
			Assert::type('list', $value);
			Assert::type(LoaderChain::class, $value[0]);
		});
	}

	public function testDefaultCache(): void
	{
		$container = $this->createContainer([], __METHOD__);
		$this->assertValidatorBuilderProperty($container, 'mappingCache', static function ($value): void {
			Assert::type(FilesystemAdapter::class, $value);
		});
	}

	public function testCustomCache(): void
	{
		$container = $this->createContainer([
			'cache' => new Statement(NullAdapter::class),
		], __METHOD__);
		$this->assertValidatorBuilderProperty($container, 'mappingCache', static function ($value): void {
			Assert::type(NullAdapter::class, $value);
		});
	}

	public function testDisableCache(): void
	{
		$container = $this->createContainer(['cache' => null], __METHOD__);
		$this->assertValidatorBuilderProperty($container, 'mappingCache', static function ($value): void {
			Assert::type(ArrayAdapter::class, $value);
		});
	}

	public function testNoTranslator(): void
	{
		$container = $this->createContainer([
			'translation' => [
				'translator' => false,
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'translator', static function ($value): void {
			Assert::null($value);
		});
	}

	public function testAutowiredTranslator(): void
	{
		$container = $this->createContainer([
			'translation' => [
				'domain' => 'validation',
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'translator', static function ($value): void {
			Assert::type(Translator::class, $value);
		});
		$this->assertValidatorBuilderProperty($container, 'translationDomain', static function ($value): void {
			Assert::same('validation', $value);
		});
	}

	public function testSpecificTranslator(): void
	{
		$container = $this->createContainer([
			'translation' => [
				'translator' => new Statement(IdentityTranslator::class),
			],
		], __METHOD__);

		$this->assertValidatorBuilderProperty($container, 'translator', static function ($value): void {
			Assert::type(IdentityTranslator::class, $value);
		});
	}

	private function assertValidatorBuilderProperty(
		Container $container,
		string $propertyName,
		Closure $assertion
	): void
	{
		$validatorBuilder = $container->getService('validator.validatorBuilder');
		$property = new ReflectionProperty($validatorBuilder, $propertyName);
		$property->setAccessible(true);
		$value = $property->getValue($validatorBuilder);

		$assertion($value);
	}

	/**
	 * @param mixed[] $config
	 */
	private function createContainer(array $config, ?string $key = null): Container
	{
		$tempDir = __DIR__ . '/../tmp/cache/nette.configurator';
		$loader = new ContainerLoader($tempDir, true);

		$containerClass = $loader->load(static function (Compiler $compiler) use ($tempDir, $config): void {
			$compiler->addExtension('validator', new ValidatorExtension());
			$compiler->addConfig(['parameters' => ['tempDir' => $tempDir], 'validator' => $config]);
			$compiler->addConfig(['services' => [new Statement(Translator::class, ['en'])]]);
		}, $key);

		return new $containerClass();
	}

}

(new ValidatorExtensionTest())->run();
