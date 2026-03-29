# Contributte Validator

Integration of [Symfony Validator](https://symfony.com/doc/current/validation.html) into Nette Framework.

## Content

- [Setup](#setup)
- [Configuration](#configuration)
- [Validation](#validation)
- [Constraints](#constraints)
- [Mapping](#mapping)
- [Translation](#translation)
- [Caching](#caching)
- [Advanced](#advanced)

---

# Setup

```bash
composer require contributte/validator
```

```neon
extensions:
	validator: Contributte\Validator\DI\ValidatorExtension
```

The extension provides sensible defaults out of the box:

- **Attribute mapping** is enabled
- **Translation** is auto-detected (if `symfony/translation` is available)
- **Cache** is stored in `%tempDir%/cache/Symfony.Validator`

That's all. You don't have to worry about anything else.

## Configuration

Minimal configuration. Just register the extension.

```neon
extensions:
	validator: Contributte\Validator\DI\ValidatorExtension
```

Full configuration:

```neon
validator:
	mapping:
		attributes: true                           # Enable PHP attributes (default: true)
		xml: []                                    # List of XML mapping files
		yaml: []                                   # List of YAML mapping files
		methods: []                                # Static methods providing metadata

	loaders: []                                    # Custom constraint loaders
	objectInitializers: []                         # Object initializers called before validation

	cache: Symfony\Component\Cache\Adapter\FilesystemAdapter  # Cache adapter

	translation:
		translator: null                           # Translator service (null = auto-detect, false = disabled)
		domain: null                               # Translation domain
```

---

# Validation

This extension exposes a configured `Symfony\Component\Validator\Validator\ValidatorInterface` in the DI container.

## Validating objects

Define validation rules using PHP attributes on your class:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class User
{
	#[Assert\NotBlank]
	#[Assert\Length(min: 3, max: 50)]
	public string $username;

	#[Assert\NotBlank]
	#[Assert\Email]
	public string $email;

	#[Assert\NotBlank]
	#[Assert\Length(min: 8)]
	public string $password;

	#[Assert\PositiveOrZero]
	public int $age;
}
```

Inject the validator and validate:

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserFacade
{

	public function __construct(
		private ValidatorInterface $validator,
	)
	{
	}

	public function register(User $user): void
	{
		$violations = $this->validator->validate($user);

		if (count($violations) > 0) {
			throw new ValidationException($violations);
		}

		// Proceed with registration...
	}

}
```

## Validating values

You can also validate individual values without an object:

```php
use Symfony\Component\Validator\Constraints as Assert;

// Single constraint
$violations = $this->validator->validate('invalid-email', new Assert\Email());

// Multiple constraints
$violations = $this->validator->validate('ab', [
	new Assert\NotBlank(),
	new Assert\Length(min: 3),
]);
```

## Working with violations

The `validate()` method returns a `ConstraintViolationListInterface`:

```php
$violations = $this->validator->validate($user);

if (count($violations) > 0) {
	foreach ($violations as $violation) {
		$violation->getPropertyPath(); // e.g. "email"
		$violation->getMessage();      // e.g. "This value is not a valid email address."
		$violation->getInvalidValue(); // The invalid value
	}
}
```

Convert violations to an array for API responses:

```php
$errors = [];

foreach ($violations as $violation) {
	$errors[$violation->getPropertyPath()][] = $violation->getMessage();
}

// ['email' => ['This value is not a valid email address.']]
```

> See [Validation](https://symfony.com/doc/current/validation.html) in Symfony docs.

---

# Constraints

## Built-in constraints

Symfony Validator provides a rich set of built-in constraints:

**Basic:** `NotBlank`, `NotNull`, `IsNull`, `IsTrue`, `IsFalse`, `Type`

**String:** `Email`, `Length`, `Url`, `Regex`, `Uuid`, `Ip`, `Json`

**Number:** `Positive`, `PositiveOrZero`, `Negative`, `NegativeOrZero`, `Range`, `LessThan`, `GreaterThan`

**Date:** `Date`, `DateTime`, `Time`, `Timezone`

**Collection:** `Count`, `Choice`, `All`, `Collection`, `UniqueEntity`

**Other:** `Valid`, `Expression`, `Callback`, `When`

> [!TIP]
> For a complete list, see the [Constraints Reference](https://symfony.com/doc/current/reference/constraints.html) in Symfony docs.

## Custom constraints

Create your own constraints. The extension **automatically autowires** all dependencies in your custom constraint validators.

**Step 1:** Create the Constraint class.

```php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class VatNumber extends Constraint
{

	public string $message = 'The value "{{ value }}" is not a valid EU VAT number.';

}
```

**Step 2:** Create the Validator class. It must be named `{ConstraintName}Validator` in the same namespace.

```php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class VatNumberValidator extends ConstraintValidator
{

	public function __construct(
		private ViesService $viesService, // <-- this dependency is automatically autowired
	)
	{
	}

	public function validate(mixed $value, Constraint $constraint): void
	{
		if (!$constraint instanceof VatNumber) {
			throw new UnexpectedTypeException($constraint, VatNumber::class);
		}

		if ($value === null || $value === '') {
			return;
		}

		if (!$this->viesService->isValid($value)) {
			$this->context->buildViolation($constraint->message)
				->setParameter('{{ value }}', $value)
				->addViolation();
		}
	}

}
```

> [!IMPORTANT]
> This extension uses a custom `ContainerConstraintValidatorFactory` that leverages Nette DI container.
> All dependencies of your constraint validators are automatically resolved. You don't need to register them manually.

**Step 3:** Use the constraint.

```php
use App\Validator\VatNumber;

final class Company
{

	#[VatNumber]
	public string $vatNumber;

}
```

> See [How to Create a Custom Validation Constraint](https://symfony.com/doc/current/validation/custom_constraint.html) in Symfony docs.

---

# Mapping

## Attribute mapping

Enabled by default. Use PHP 8 attributes to define validation rules directly on your classes:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class Product
{

	#[Assert\NotBlank]
	#[Assert\Length(min: 3, max: 255)]
	public string $name;

	#[Assert\NotBlank]
	#[Assert\Positive]
	public float $price;

	#[Assert\Valid]
	public Category $category;

}
```

To disable attribute mapping:

```neon
validator:
	mapping:
		attributes: false
```

## XML mapping

Define constraints in XML files for cases where you can't modify entity classes:

```neon
validator:
	mapping:
		xml:
			- %appDir%/config/validation/user.xml
			- %appDir%/config/validation/product.xml
```

Example XML mapping file:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<constraint-mapping xmlns="http://symfony.com/schema/dic/constraint-mapping"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:schemaLocation="http://symfony.com/schema/dic/constraint-mapping
		https://symfony.com/schema/dic/constraint-mapping/constraint-mapping-1.0.xsd">

	<class name="App\Entity\User">
		<property name="username">
			<constraint name="NotBlank"/>
			<constraint name="Length">
				<option name="min">3</option>
				<option name="max">50</option>
			</constraint>
		</property>
		<property name="email">
			<constraint name="NotBlank"/>
			<constraint name="Email"/>
		</property>
	</class>
</constraint-mapping>
```

## YAML mapping

Define constraints in YAML files:

```neon
validator:
	mapping:
		yaml:
			- %appDir%/config/validation.yaml
```

Example YAML mapping file:

```yaml
App\Entity\User:
	properties:
		username:
			- NotBlank: ~
			- Length:
				min: 3
				max: 50
		email:
			- NotBlank: ~
			- Email:
				mode: strict
		password:
			- NotBlank: ~
			- Length:
				min: 8
```

## Method mapping

For dynamic constraints, use a static method in your class:

```neon
validator:
	mapping:
		methods:
			- loadValidatorMetadata
```

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

final class User
{

	public string $username;
	public string $email;

	public static function loadValidatorMetadata(ClassMetadata $metadata): void
	{
		$metadata->addPropertyConstraint('username', new Assert\NotBlank());
		$metadata->addPropertyConstraint('username', new Assert\Length(min: 3, max: 50));

		$metadata->addPropertyConstraint('email', new Assert\NotBlank());
		$metadata->addPropertyConstraint('email', new Assert\Email());
	}

}
```

## Custom loaders

For advanced scenarios, you can register custom metadata loaders:

```neon
validator:
	loaders:
		- App\Validator\Loader\DatabaseLoader
```

```php
namespace App\Validator\Loader;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

final class DatabaseLoader implements LoaderInterface
{

	public function __construct(
		private Database $database,
	)
	{
	}

	public function loadClassMetadata(ClassMetadata $metadata): bool
	{
		// Load constraints from database dynamically
		$rules = $this->database->table('validation_rules')
			->where('class', $metadata->getClassName())
			->fetchAll();

		foreach ($rules as $rule) {
			// Apply constraints...
		}

		return count($rules) > 0;
	}

}
```

> See [Loading Class Metadata](https://symfony.com/doc/current/validation.html#loading-class-metadata) in Symfony docs.

---

# Translation

Validation error messages can be translated using Symfony's translation component.

## Auto-detection

If you have `symfony/translation` installed and a translator service is available in the container (e.g. via [contributte/translation](https://github.com/contributte/translation)), it will be auto-detected and used automatically.

```bash
composer require contributte/translation
```

```neon
extensions:
	translation: Contributte\Translation\DI\TranslationExtension

translation:
	locales:
		default: en
		fallback: [en]
	dirs:
		- %appDir%/locale
```

## Custom translator

Specify a custom translator service:

```neon
validator:
	translation:
		translator: @myCustomTranslator
```

## Translation domain

Set a custom translation domain for validation messages:

```neon
validator:
	translation:
		domain: validators
```

## Disabling translation

To disable translation entirely:

```neon
validator:
	translation:
		translator: false
```

---

# Caching

Validation metadata (constraints defined on classes) is cached to improve performance.

By default, metadata is cached in `%tempDir%/cache/Symfony.Validator/` using `FilesystemAdapter`.

**Redis cache:**

```neon
validator:
	cache: Symfony\Component\Cache\Adapter\RedisAdapter(@redis.client, 'validator')
```

**Array cache** (for testing):

```neon
validator:
	cache: Symfony\Component\Cache\Adapter\ArrayAdapter
```

**Disable cache:**

```neon
validator:
	cache: null
```

> [!IMPORTANT]
> You should always use cache for production environment. It can significantly improve performance of your application.

---

# Advanced

## Validation groups

Use validation groups to apply different constraints in different contexts:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class User
{

	#[Assert\NotBlank(groups: ['registration', 'profile'])]
	#[Assert\Email(groups: ['registration', 'profile'])]
	public string $email;

	#[Assert\NotBlank(groups: ['registration'])]
	#[Assert\Length(min: 8, groups: ['registration', 'password_change'])]
	public string $password;

	#[Assert\NotBlank(groups: ['profile'])]
	public string $name;

}
```

```php
// Only validate 'registration' group
$violations = $this->validator->validate($user, null, ['registration']);

// Validate multiple groups
$violations = $this->validator->validate($user, null, ['registration', 'strict']);
```

> See [Validation Groups](https://symfony.com/doc/current/validation/groups.html) in Symfony docs.

## Cascading validation

Use `#[Assert\Valid]` to validate nested objects:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class Order
{

	#[Assert\Valid]
	public Address $shippingAddress;

	#[Assert\Valid]
	#[Assert\Count(min: 1)]
	public array $items;

}
```

> See [Validating Object Constraints](https://symfony.com/doc/current/validation.html#validating-object-constraints) in Symfony docs.

## Object initializers

Object initializers are called before validation. Use them to prepare objects:

```neon
validator:
	objectInitializers:
		- App\Validator\Initializer\DoctrineInitializer
```

```php
namespace App\Validator\Initializer;

use Symfony\Component\Validator\ObjectInitializerInterface;

final class DoctrineInitializer implements ObjectInitializerInterface
{

	public function initialize(object $object): void
	{
		// Initialize lazy-loaded Doctrine proxies before validation
		if ($object instanceof \Doctrine\Persistence\Proxy) {
			$object->__load();
		}
	}

}
```

## Expression constraint

Use the Expression constraint for complex cross-field validation logic:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class Event
{

	#[Assert\NotBlank]
	public \DateTimeInterface $startDate;

	#[Assert\NotBlank]
	#[Assert\Expression(
		expression: 'value > this.startDate',
		message: 'End date must be after start date.',
	)]
	public \DateTimeInterface $endDate;

}
```

> [!NOTE]
> The Expression constraint requires `symfony/expression-language`:
> ```bash
> composer require symfony/expression-language
> ```

> See [Expression](https://symfony.com/doc/current/reference/constraints/Expression.html) in Symfony docs.
