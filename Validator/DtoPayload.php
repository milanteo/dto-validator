<?php

namespace Teoalboo\DtoValidator\Validator;

use Teoalboo\DtoValidator\DtoField;
use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;
use Attribute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;


#[Attribute(Attribute::TARGET_CLASS)]
class DtoPayload extends Constraint {

    public array $properties = [];

    public array $disabled = [];

    public array $errors = [];
    
    #[HasNamedArguments]
    public function __construct(
        public int $errorCode = Response::HTTP_BAD_REQUEST,
        public mixed $subject = null,
        public ?object $content = null,
        public ?array  $fields  = null
    ) {

        parent::__construct([]);
    }
    
    public function getTargets(): string | array {

        return self::CLASS_CONSTRAINT;
    }

    public function getEnabledParameters(bool $includeErrorProperties = false): array {

        return array_intersect_key(get_object_vars($this->content), $this->getEnabledProperties($includeErrorProperties));
    }

    public function getPropertiesValues(object $dto): array {

        return array_intersect_key(get_object_vars($dto), $this->getEnabledParameters(includeErrorProperties: true));
    }

    public function disableProperties(array $properties): self {

        $this->disabled = array_merge($this->disabled, $properties);

        return $this;
    }

    public function getPropertyAttribute(string $propertyName): DtoField {

        return $this->properties[$propertyName];
    }

    public function getEnabledProperties(bool $includeErrorProperties = false): array {

        $notValid = array_merge(array_keys($this->disabled), $includeErrorProperties ? [] : $this->getErrorProperties());

        return array_filter($this->properties, fn($property) => !in_array($property, $notValid), ARRAY_FILTER_USE_KEY);
    }

    public function addError(DtoFieldValidationException $e): self {

        $this->errors[] = $e;

        return $this;
    }

    public function getErrorProperties(): array {

        return array_map(fn(DtoFieldValidationException $e) => $e->getPropertyPath(), $this->getAllErrors());
    }

    public function getAllErrors(): array {

        // Vengono ritornati i soli errori riguardanti le proprietà non disabilitate
        return array_filter($this->errors, fn(DtoFieldValidationException $e) => !in_array($e->getPropertyPath(), array_keys($this->disabled)));
    }

}
