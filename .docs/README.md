# Validator

## Contents

- [Setup](#setup)
    - [Configuration](#configuration)
- [Usage](#usage)

## Setup

Require package:

```bash
composer require contributte/validator
```

Register extension:

```yaml
extensions:
    validator: Contributte\Validator\DI\ValidatorExtension
```

### Configuration

The extension tries to provide sane defaults so that in most common cases, it works out-of-the-box without the need for further configuration:

- validation errors are translated if `symfony/translation` is installed and configured (see [contributte/translation](https://github.com/contributte/translation));
- annotation mapping is enabled as long as the `doctrine/annotations` package is installed (see [nettrine/annotations](https://github.com/nettrine/annotations));
- mapping cache is stored in `%tempDir%/cache/Symfony.Validator` by default.

If you're not satisfied with these defaults, you can add or override some of the options:

```yaml
validator:
    # configure various mapping loaders
    mapping:
        xml: [/path/to/mapping.xml, /path/to/another/mapping.xml]
        yaml: [/path/to/mapping.yml, /path/to/another/mapping.yml]
        methods: [loadValidatorMetadataMethodName]

    # use a different mapping cache implementation
    cache: Symfony\Component\Cache\Adapter\RedisAdapter(@redis.client)

    # configure translator and/or translation domain...
    translation:
        translator: My\Translator
        domain: validator

    # ...or disable translation entirely
    translation:
        translator: false
```

## Usage

This extension exposes a configured implementation of `Symfony\Component\Validator\Validator\ValidatorInterface` in the dependency injection container:

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MyServiceThatNeedsToValidateStuff
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }
}
```

### Custom constraint validators

If you implement custom constrains and constraint validators, this extension makes sure that all dependencies of the validator constructor are autowired and everything just works:

```php
class VatNumber extends \Symfony\Component\Validator\Constraint {}

class VatNumberValidator extends \Symfony\Component\Validator\ConstraintValidator
{
    private ViesService $vies;

    public function __construct(ViesService $vies) // <-- this dependency is automatically autowired
    {
        $this->vies = $vies;
    }

    public function validate($value,\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$this->vies->validate($value)) {
            $this->context->buildViolation('Value "{{ value }}" is not a valid EU VAT number.')
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
```
