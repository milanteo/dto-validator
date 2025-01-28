<?php

namespace Teoalboo\DtoValidator;

use AllowDynamicProperties;
use Ds\Map;
use ReflectionClass;
use stdClass;
use Teoalboo\DtoValidator\Attribute\DtoField;
use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;

#[AllowDynamicProperties]
class BaseDto {

    public function __construct(
        ?object $content    = null,
        ?array  $properties = null
    ) {

        $this->initDtoFields($properties ?? $this->calcDtoProperties());

        $this->setContent($content ?? new stdClass());
        
    }

    public function getValues(): Map {

        return new Map(array_map(fn(DtoField $v) => $v->getValue(), $this->getInitializedProperties()));
    }

    public function getRawValues(): Map {

        return new Map(array_map(fn(DtoField $v) => $v->getRawValue(), $this->getInitializedProperties()));
    }

    public function getInitializedProperties(bool $includeErrorProperties = false): array {

        return array_filter($this->getEnabledProperties($includeErrorProperties), fn(DtoField $v) => $v->isInitialized());
    }

    public function getEnabledProperties(bool $includeErrorProperties = false): array {

        return array_filter($this->getProperties(), fn(DtoField $v) => $includeErrorProperties ? !$v->isDisabled() : !$v->isDisabled() && !$v->getErrors());
    }

    protected function getProperties(): array {

        return array_filter(get_object_vars($this), fn(mixed $v) => $v instanceof DtoField);
    }

    public function disableProperty(string $property): self {

        foreach ($this->getProperties() as $propertyName => $attribute) {
            
            if($propertyName == $property || in_array($property, $attribute->disabledWith)) {

                $attribute->disable();
            }

        }

        return $this;
    }

    public function addPropertyErrors(DtoFieldValidationException $e): self {

        $this->{$e->getPropertyPath()}->addErrors($e->getMessages());

        return $this;
    }

    public function getAllErrors(): array {

        $errors = [];

        foreach ($this->getEnabledProperties(includeErrorProperties: true) as $propertyName => $attribute) {
            
            if($propsErrors = $attribute->getErrors()) {

                $errors[$propertyName] = $propsErrors;
            }
        }

        return $errors;
    }

    protected function initDtoFields(array $properties): void {

        foreach ($properties as $propertyName => $propertyAttribute) {

            $this->$propertyName = $propertyAttribute;
            
        }

    }

    public function setContent(object $content): void {

        foreach ($this->getProperties() as $propertyName => $propertyAttribute) {

            if(property_exists($content, $propertyName)) {

                $propertyAttribute->setRawValue($content->$propertyName);
            }
            
        }

    }

    protected function calcDtoProperties(): array {

        $properties = [];

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {

            if(count($attributes = $property->getAttributes(DtoField::class))) {

                [ $attribute ] = $attributes;

                $properties[$property->getName()] = $attribute->newInstance();

            }
            
        }

        return $properties;

    }

    public function additionalExpressionContext(): array {

        return [];
    }

}
