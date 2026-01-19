# Contributte Validator

[Symfony Validator](https://symfony.com/doc/current/validation.html) integration for [Nette Framework](https://nette.org).

## Contents

- [Setup](#setup)
- [Configuration](#configuration)
- [Validation](#validation)
  - [Basic Usage](#basic-usage)
  - [Validating Objects](#validating-objects)
  - [Validating Values](#validating-values)
  - [Working with Violations](#working-with-violations)
- [Constraints](#constraints)
  - [Built-in Constraints](#built-in-constraints)
  - [Custom Constraints](#custom-constraints)
  - [Constraint Options](#constraint-options)
- [Mapping](#mapping)
  - [Attribute Mapping](#attribute-mapping)
  - [XML Mapping](#xml-mapping)
  - [YAML Mapping](#yaml-mapping)
  - [Method Mapping](#method-mapping)
  - [Custom Loaders](#custom-loaders)
- [Translation](#translation)
  - [Auto-detection](#auto-detection)
  - [Custom Translator](#custom-translator)
  - [Translation Domain](#translation-domain)
  - [Disabling Translation](#disabling-translation)
- [Caching](#caching)
  - [Filesystem Cache](#filesystem-cache)
  - [Redis Cache](#redis-cache)
  - [Array Cache](#array-cache)
  - [Disabling Cache](#disabling-cache)
- [Advanced](#advanced)
  - [Object Initializers](#object-initializers)
  - [Validation Groups](#validation-groups)
  - [Cascading Validation](#cascading-validation)
  - [Expression Constraint](#expression-constraint)

## Setup

Install package:

```bash
composer require contributte/validator
```

Register extension:

```neon
extensions:
    validator: Contributte\Validator\DI\ValidatorExtension
```

## Configuration

Minimal configuration:

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
        domain: validators                         # Translation domain
```

## Validation

This extension exposes a configured implementation of `Symfony\Component\Validator\Validator\ValidatorInterface` in the DI container.

### Basic Usage

Inject the validator into your service:

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserService
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    public function registerUser(User $user): void
    {
        $violations = $this->validator->validate($user);

        if (count($violations) > 0) {
            // Handle validation errors
            throw new ValidationException($violations);
        }

        // Proceed with registration...
    }
}
```

### Validating Objects

Define validation rules using PHP attributes on your entity:

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
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'Password must contain at least one lowercase letter, one uppercase letter, and one digit.',
    )]
    public string $password;

    #[Assert\PositiveOrZero]
    public int $age;
}
```

Validate the object:

```php
$user = new User();
$user->username = 'ab'; // Too short
$user->email = 'invalid'; // Invalid email
$user->password = 'weak'; // Doesn't meet requirements
$user->age = -5; // Negative

$violations = $this->validator->validate($user);
// Returns 4 violations
```

### Validating Values

You can also validate individual values without an object:

```php
use Symfony\Component\Validator\Constraints as Assert;

// Validate a single value
$violations = $this->validator->validate(
    'invalid-email',
    new Assert\Email(),
);

// Validate with multiple constraints
$violations = $this->validator->validate(
    'ab',
    [
        new Assert\NotBlank(),
        new Assert\Length(min: 3),
    ],
);
```

### Working with Violations

The `validate()` method returns a `ConstraintViolationListInterface`:

```php
$violations = $this->validator->validate($user);

if (count($violations) > 0) {
    foreach ($violations as $violation) {
        // Get the property path (e.g., "email", "address.city")
        $propertyPath = $violation->getPropertyPath();

        // Get the error message
        $message = $violation->getMessage();

        // Get the invalid value
        $invalidValue = $violation->getInvalidValue();

        // Get the message template (before translation)
        $template = $violation->getMessageTemplate();

        // Get the constraint that caused the violation
        $constraint = $violation->getConstraint();
    }
}
```

Convert violations to an array for API responses:

```php
$errors = [];

foreach ($violations as $violation) {
    $errors[$violation->getPropertyPath()][] = $violation->getMessage();
}

// Result: ['email' => ['This value is not a valid email address.']]
```

## Constraints

### Built-in Constraints

Symfony Validator provides a comprehensive set of built-in constraints. Here are some commonly used ones:

**Basic Constraints:**
- `NotBlank` - Value must not be blank
- `NotNull` - Value must not be null
- `IsNull` - Value must be null
- `IsTrue` / `IsFalse` - Value must be true/false
- `Type` - Value must be of a specific type

**String Constraints:**
- `Email` - Valid email address
- `Length` - String length within range
- `Url` - Valid URL
- `Regex` - Matches a regular expression
- `Uuid` - Valid UUID

**Number Constraints:**
- `Positive` / `PositiveOrZero` - Positive numbers
- `Negative` / `NegativeOrZero` - Negative numbers
- `Range` - Value within a range
- `LessThan` / `GreaterThan` - Comparison constraints

**Date Constraints:**
- `Date` / `DateTime` / `Time` - Valid date/time formats
- `Timezone` - Valid timezone

**Collection Constraints:**
- `Count` - Collection size
- `Choice` - Value from a list of choices
- `All` - Apply constraints to all collection items
- `Collection` - Validate array keys

**Other Constraints:**
- `Valid` - Cascade validation to nested objects
- `Expression` - Custom expression-based validation
- `Callback` - Custom callback validation

For a complete list, see the [Symfony Validator Constraints Reference](https://symfony.com/doc/current/reference/constraints.html).

### Custom Constraints

Create your own constraints by extending `Symfony\Component\Validator\Constraint`:

**Step 1: Create the Constraint class**

```php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class VatNumber extends Constraint
{
    public string $message = 'The value "{{ value }}" is not a valid EU VAT number.';
}
```

**Step 2: Create the Validator class**

The validator class must be named `{ConstraintName}Validator` and placed in the same namespace:

```php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class VatNumberValidator extends ConstraintValidator
{
    public function __construct(
        private ViesService $viesService, // Dependencies are autowired!
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof VatNumber) {
            throw new UnexpectedTypeException($constraint, VatNumber::class);
        }

        // Allow empty values - use NotBlank for required fields
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
> This extension automatically autowires all dependencies in your custom constraint validators. The `ViesService` in the example above will be injected from the DI container.

**Step 3: Use the Constraint**

```php
use App\Validator\VatNumber;

final class Company
{
    #[VatNumber]
    public string $vatNumber;
}
```

### Constraint Options

You can make your constraints configurable:

```php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ContainsAlphanumeric extends Constraint
{
    public string $message = 'The string "{{ string }}" contains invalid characters.';
    public bool $allowSpaces = false;
    public int $minLength = 1;

    public function __construct(
        ?string $message = null,
        ?bool $allowSpaces = null,
        ?int $minLength = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->allowSpaces = $allowSpaces ?? $this->allowSpaces;
        $this->minLength = $minLength ?? $this->minLength;
    }
}
```

Usage:

```php
#[ContainsAlphanumeric(allowSpaces: true, minLength: 5)]
public string $code;
```

## Mapping

### Attribute Mapping

Attribute mapping is enabled by default. Use PHP 8 attributes to define validation rules directly on your classes:

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

    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    public int $stock;

    // Validate nested object
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

### XML Mapping

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
            <constraint name="Email">
                <option name="mode">strict</option>
            </constraint>
        </property>

        <property name="roles">
            <constraint name="Count">
                <option name="min">1</option>
            </constraint>
            <constraint name="All">
                <option name="constraints">
                    <constraint name="Choice">
                        <option name="choices">
                            <value>ROLE_USER</value>
                            <value>ROLE_ADMIN</value>
                            <value>ROLE_MODERATOR</value>
                        </option>
                    </constraint>
                </option>
            </constraint>
        </property>
    </class>
</constraint-mapping>
```

### YAML Mapping

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
            - Regex:
                pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'
                message: 'Password must contain mixed case and numbers.'
```

### Method Mapping

For dynamic constraints, use a static method in your class:

```neon
validator:
    mapping:
        methods:
            - loadValidatorMetadata
```

Implement the method in your entity:

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

final class User
{
    public string $username;
    public string $email;
    public array $roles;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('username', new Assert\NotBlank());
        $metadata->addPropertyConstraint('username', new Assert\Length(min: 3, max: 50));

        $metadata->addPropertyConstraint('email', new Assert\NotBlank());
        $metadata->addPropertyConstraint('email', new Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT));

        // Add constraints conditionally
        if (getenv('STRICT_VALIDATION')) {
            $metadata->addPropertyConstraint('roles', new Assert\Count(min: 1));
        }
    }
}
```

### Custom Loaders

For advanced scenarios, you can create custom metadata loaders:

```neon
validator:
    loaders:
        - App\Validator\Loader\DatabaseLoader
        - App\Validator\Loader\ApiLoader(@httpClient)
```

```php
namespace App\Validator\Loader;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

final class DatabaseLoader implements LoaderInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        // Load constraints from database
        $rules = $this->database->table('validation_rules')
            ->where('class', $metadata->getClassName())
            ->fetchAll();

        foreach ($rules as $rule) {
            // Apply constraints dynamically
        }

        return count($rules) > 0;
    }
}
```

## Translation

Validation error messages can be translated using Symfony's translation component.

### Auto-detection

If you have `symfony/translation` installed and a translator service is available in the container (e.g., via [contributte/translation](https://github.com/contributte/translation)), it will be auto-detected and used:

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

### Custom Translator

Specify a custom translator service:

```neon
validator:
    translation:
        translator: @myCustomTranslator
```

Or create a new translator instance:

```neon
validator:
    translation:
        translator: App\Translation\MyTranslator(@someService)
```

### Translation Domain

Set a custom translation domain for validation messages:

```neon
validator:
    translation:
        domain: validators
```

Create translation files with your domain. For example, `locale/validators.en.neon`:

```neon
This value should not be blank.: Please fill in this field.
This value is not a valid email address.: Please enter a valid email address.
This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.: Please enter at least {{ limit }} characters.
```

### Disabling Translation

To disable translation entirely:

```neon
validator:
    translation:
        translator: false
```

## Caching

Validation metadata (constraints defined on classes) is cached to improve performance. The cache stores the parsed constraint information so it doesn't need to be re-read on every request.

### Filesystem Cache

The default cache stores metadata in the filesystem:

```neon
validator:
    cache: Symfony\Component\Cache\Adapter\FilesystemAdapter
```

This stores cache files in `%tempDir%/cache/Symfony.Validator/`.

### Redis Cache

For distributed applications or better performance:

```neon
services:
    redis.client: Redis()::connect('127.0.0.1', 6379)

validator:
    cache: Symfony\Component\Cache\Adapter\RedisAdapter(@redis.client, 'validator')
```

### Array Cache

For testing or development, use in-memory cache (not persisted):

```neon
validator:
    cache: Symfony\Component\Cache\Adapter\ArrayAdapter
```

### Disabling Cache

To disable caching entirely (not recommended for production):

```neon
validator:
    cache: null
```

## Advanced

### Object Initializers

Object initializers are called before validation. Use them to prepare objects:

```neon
validator:
    objectInitializers:
        - App\Validator\Initializer\DoctrineInitializer
```

```php
namespace App\Validator\Initializer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;

final class DoctrineInitializer implements ObjectInitializerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function initialize(object $object): void
    {
        // Initialize lazy-loaded Doctrine proxies before validation
        if ($object instanceof \Doctrine\Persistence\Proxy) {
            $object->__load();
        }
    }
}
```

### Validation Groups

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

Validate with specific groups:

```php
// Only validate 'registration' group constraints
$violations = $this->validator->validate($user, null, ['registration']);

// Validate 'Default' group (constraints without explicit groups)
$violations = $this->validator->validate($user, null, ['Default']);

// Validate multiple groups
$violations = $this->validator->validate($user, null, ['registration', 'strict']);
```

### Cascading Validation

Use `#[Assert\Valid]` to validate nested objects:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class Order
{
    #[Assert\NotBlank]
    public string $orderNumber;

    #[Assert\Valid] // Validates the Address object
    public Address $shippingAddress;

    #[Assert\Valid] // Validates each item in the collection
    #[Assert\Count(min: 1)]
    public array $items;
}

final class Address
{
    #[Assert\NotBlank]
    public string $street;

    #[Assert\NotBlank]
    public string $city;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    public string $postalCode;
}

final class OrderItem
{
    #[Assert\NotBlank]
    public string $productId;

    #[Assert\Positive]
    public int $quantity;
}
```

### Expression Constraint

Use the Expression constraint for complex validation logic:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class Discount
{
    public string $type; // 'percentage' or 'fixed'

    #[Assert\Positive]
    #[Assert\Expression(
        expression: 'this.type != "percentage" or value <= 100',
        message: 'Percentage discount cannot exceed 100%.',
    )]
    public float $value;
}
```

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
> The Expression constraint uses Symfony's ExpressionLanguage component. You may need to install it separately:
> ```bash
> composer require symfony/expression-language
> ```
