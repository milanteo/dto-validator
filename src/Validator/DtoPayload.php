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

    public function getPropertiesValues(object $dto): array {

        return array_intersect_key(get_object_vars($dto), $this->getEnabledParameters(includeErrorProperties: true));
    }

    public function getEnabledParameters(bool $includeErrorProperties = false): array {

        return array_intersect_key(get_object_vars($this->content), $this->getEnabledProperties($includeErrorProperties));
    }

    public function getEnabledProperties(bool $includeErrorProperties = false): array {

        return array_filter($this->properties, fn(DtoField $attr) => $includeErrorProperties ? !$attr->isDisabled : !$attr->isDisabled && !$attr->errors);
    }

    public function disableProperty(string $property): self {

        foreach ($this->properties as $propertyName => $attribute) {
            
            if($propertyName == $property || in_array($property, $attribute->disabledWith)) {

                $attribute->isDisabled = true;
            }

        }

        return $this;
    }

    public function getPropertyAttribute(string $propertyName): DtoField {

        return $this->properties[$propertyName];
    }

    public function addError(DtoFieldValidationException $e): self {

        array_push($this->properties[$e->getPropertyPath()]->errors, ...$e->getMessages());

        return $this;
    }

    public function getPayloadErrors(): array {

        $errors = [];

        foreach ($this->properties as $propertyName => $attribute) {
            
            // Vengono ritornati i soli errori riguardanti le proprietà non disabilitate
            if($attribute->errors && !$attribute->isDisabled) {

                $errors[$propertyName] = $attribute->errors;
            }
        }

        return $errors;
    }

}
