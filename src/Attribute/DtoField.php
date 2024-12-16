<?php
namespace Teoalboo\DtoValidator\Attribute;

use Attribute;
use Teoalboo\DtoValidator\Resolver\DtoResolverInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DtoField {

    private bool $isDisabled = false;

    private bool $initialized = false;

    private array $errors = [];

    private mixed $value = null;

    public function __construct(
        public array $types,
        public ?DtoResolverInterface $resolver = null,
        public array $constraints = [],
        public bool | string $nullable = false,
        public bool | string $required = false,
        public bool | string $disabled = false,
        public array $disabledWith = []
    ) { }

    public function isDisabled(): bool {

        return $this->isDisabled;
    }

    public function disable(): self {

        $this->isDisabled = true;

        return $this;
    }

    public function enable(): self {

        $this->isDisabled = false;

        return $this;
    }

    public function getValue(): mixed {

        return $this->value;
    }

    public function isInitialized(): bool {

        return $this->initialized;
    }

    public function setValue(mixed $value): self {

        $this->initialized = true;

        $this->value = $value;

        return $this;
    }

    public function getErrors(): array {

        return $this->errors;
    }

    public function addErrors(array $errors): self {

        $this->errors = array_merge($this->errors, $errors);

        return $this;

    } 

}