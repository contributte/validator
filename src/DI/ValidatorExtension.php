<?php declare(strict_types = 1);

namespace Contributte\Validator\DI;

use Contributte\Validator\ContainerConstraintValidatorFactory;
use Doctrine\Common\Annotations\Reader;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;
use function assert;
use function interface_exists;
use function is_string;

/**
 * @property stdClass $config
 */
final class ValidatorExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'mapping' => Expect::structure([
				'annotations' => Expect::bool(interface_exists(Reader::class)),
				'xml' => Expect::listOf(Expect::string()->dynamic()),
				'yaml' => Expect::listOf(Expect::string()->dynamic()),
				'methods' => Expect::listOf(Expect::string()->dynamic()),
			]),
			'loaders' => Expect::listOf(
				Expect::anyOf(Expect::string(), Expect::type(Statement::class))
			),
			'objectInitializers' => Expect::listOf(
				Expect::anyOf(Expect::string(), Expect::type(Statement::class))
			),
			'cache' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable()->default(FilesystemAdapter::class),
			'translation' => Expect::structure([
				'translator' => Expect::anyOf(Expect::string(), Expect::type(Statement::class), false)->nullable(),
				'domain' => Expect::string()->nullable(),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$containerBuilder = $this->getContainerBuilder();

		$validatorBuilder = $containerBuilder->addDefinition($this->prefix('validatorBuilder'), new ServiceDefinition())
			->setFactory(ValidatorBuilder::class)
			->addSetup('setConstraintValidatorFactory', [new Statement(ContainerConstraintValidatorFactory::class)])
			->setAutowired(false);

		$this->setupCache($validatorBuilder);
		$this->setupLoaders($validatorBuilder);
		$this->setupObjectInitializers($validatorBuilder);

		$containerBuilder->addDefinition($this->prefix('validator'), new ServiceDefinition())
			->setType(ValidatorInterface::class)
			->setFactory([$validatorBuilder, 'getValidator']);
	}


	public function beforeCompile(): void
	{
		$validatorBuilder = $this->getContainerBuilder()->getDefinition($this->prefix('validatorBuilder'));
		assert($validatorBuilder instanceof ServiceDefinition);
		$this->setupTranslator($validatorBuilder);
		$this->setupMapping($validatorBuilder);
	}

	private function setupMapping(ServiceDefinition $validatorBuilder): void
	{
		$validatorBuilder->addSetup('enableAnnotationMapping', [true]);

		if ($this->config->mapping->annotations) {
			if ($this->getContainerBuilder()->findByType(Reader::class)) {
				$validatorBuilder->addSetup('setDoctrineAnnotationReader');
			} else {
				$validatorBuilder->addSetup('addDefaultDoctrineAnnotationReader');
			}
		}

		$validatorBuilder->addSetup('addXmlMappings', [$this->config->mapping->xml]);
		$validatorBuilder->addSetup('addYamlMappings', [$this->config->mapping->yaml]);
		$validatorBuilder->addSetup('addMethodMappings', [$this->config->mapping->methods]);
	}

	private function setupCache(ServiceDefinition $validatorBuilder): void
	{
		if ($this->config->cache instanceof Statement) {
			$cache = $this->config->cache;

		} elseif ($this->config->cache === FilesystemAdapter::class) {
			$cache = new Statement(
				FilesystemAdapter::class,
				[
					'namespace' => 'Symfony.Validator',
					'directory' => $this->getContainerBuilder()->parameters['tempDir'] . '/cache',
				]
			);

		} elseif (is_string($this->config->cache)) {
			$cache = new Statement($this->config->cache);

		} else {
			$cache = new Statement(ArrayAdapter::class);
		}

		$validatorBuilder->addSetup('setMappingCache', [$cache]);
	}

	private function setupLoaders(ServiceDefinition $validatorBuilder): void
	{
		foreach ($this->config->loaders as $loader) {
			if (is_string($loader)) {
				$loader = new Statement($loader);
			}

			$validatorBuilder->addSetup('addLoader', [$loader]);
		}
	}

	private function setupObjectInitializers(ServiceDefinition $validatorBuilder): void
	{
		foreach ($this->config->objectInitializers as $objectInitializer) {
			if (is_string($objectInitializer)) {
				$objectInitializer = new Statement($objectInitializer);
			}

			$validatorBuilder->addSetup('addObjectInitializer', [$objectInitializer]);
		}
	}

	private function setupTranslator(ServiceDefinition $validatorBuilder): void
	{
		if ($this->config->translation->translator === false) {
			return;
		}

		if ($this->config->translation->domain !== null) {
			$validatorBuilder->addSetup('setTranslationDomain', [$this->config->translation->domain]);
		}

		if ($this->config->translation->translator !== null) {
			$translator = $this->config->translation->translator;

			if (is_string($translator)) {
				$translator = new Statement($translator);
			}

			$validatorBuilder->addSetup('setTranslator', [$translator]);

			return;
		}

		$containerBuilder = $this->getContainerBuilder();
		$symfonyTranslator = $containerBuilder->getByType(TranslatorInterface::class);

		if ($symfonyTranslator !== null) {
			$validatorBuilder->addSetup('setTranslator', [$containerBuilder->getDefinition($symfonyTranslator)]);

			return;
		}
	}

}
