<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use stdClass;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class DummyConstraintValidator extends ConstraintValidator
{

	public stdClass $dependency;

	public function __construct(stdClass $dependency)
	{
		$this->dependency = $dependency;
	}

	public function validate(mixed $value, Constraint $constraint): void
	{
		// Dummy implementation for testing purposes
	}

}
