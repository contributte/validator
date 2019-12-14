<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use stdClass;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class DummyConstraintValidator extends ConstraintValidator
{

	/** @var stdClass */
	public $dependency;

	public function __construct(stdClass $dependency)
	{
		$this->dependency = $dependency;
	}

	/**
	 * @param mixed $value
	 */
	public function validate($value, Constraint $constraint): void
	{
	}

}
