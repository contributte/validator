<?php declare(strict_types = 1);

namespace Contributte\Validator;

use Nette\DI\Container;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ValidatorException;
use function class_exists;
use function get_class;
use function sprintf;

final class ContainerConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{

	private Container $container;

	/** @var ConstraintValidatorInterface[] */
	private array $validators;

	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->validators = [];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws ValidatorException      when the validator class does not exist
	 * @throws UnexpectedTypeException when the validator is not an instance of ConstraintValidatorInterface
	 */
	public function getInstance(Constraint $constraint): ConstraintValidatorInterface
	{
		/** @var class-string<ConstraintValidatorInterface> $name */
		$name = $constraint->validatedBy() === 'validator.expression'
			? ExpressionValidator::class
			: $constraint->validatedBy();

		if (!isset($this->validators[$name])) {
			$validator = $this->container->getByType($name, false);

			if ($validator !== null) {
				$this->validators[$name] = $validator;

			} else {
				if (!class_exists($name)) {
					throw new ValidatorException(sprintf('Constraint validator "%s" does not exist or is not enabled. Check the "validatedBy" method in your constraint class "%s".', $name, get_class($constraint)));
				}

				$this->validators[$name] = $this->container->createInstance($name);
			}
		}

		if (!$this->validators[$name] instanceof ConstraintValidatorInterface) {
			throw new UnexpectedTypeException($this->validators[$name], ConstraintValidatorInterface::class);
		}

		return $this->validators[$name];
	}

}
