<?php

namespace Teoalboo\DtoValidator\Service;

use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;
use Psr\Container\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Teoalboo\DtoValidator\Attribute\DtoField;
use Teoalboo\DtoValidator\DtoFieldType;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Teoalboo\DtoValidator\Attribute\DtoPayload;
use Teoalboo\DtoValidator\BaseDto;
use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;

use function Symfony\Component\String\s;

class DtoValidatorService {

    public function __construct(
        private ContainerInterface $container,
        private ValidatorInterface $validator
    ) { }

    public function validate(BaseDto $dto, DtoPayload $attribute): void {

        $this->checkValues($dto, $attribute);
        
        $this->checkDisabledProperties($dto, $attribute);
        
        $this->checkNullableProperties($dto, $attribute);
        
        $this->checkRequiredFields($dto, $attribute);

        $this->applyConstraints($dto, $attribute);

        $this->throwErrors($dto, $attribute);

    }

    private function throwErrors(BaseDto $dto, DtoPayload $constraint): void {

        if($errors = $dto->getAllErrors()) {

            throw new DtoPayloadValidationException($errors, $constraint->getErrorCode());
        }

    }

    function checkValues(BaseDto $dto, DtoPayload $constraint): void {
        
        foreach ($dto->getInitializedProperties() as $property => $attribute) {

            try {

                $rawValue = $this->checkRawValueType($property, $attribute);

                if(is_null($rawValue) || is_null($attribute->resolver)) { 

                    $attribute->setValue($rawValue);
                } else {

                    $resolver = $this->container->get($attribute->resolver->resolvedBy());
                    
                    $attribute->setValue($resolver->resolve($attribute->resolver, $property, $rawValue));
                }

            } catch(DtoFieldValidationException $e) {

                $dto->addPropertyErrors($e);

            }

        }


    }

    function checkDisabledProperties(BaseDto $dto, DtoPayload $constraint, array &$checkedFields = []): void {

        $currentCheck = array_filter($dto->getEnabledProperties(includeErrorProperties: true), function($attribute, $propertyName) use (&$checkedFields) {

            if(in_array($propertyName, array_keys($checkedFields))) {

                return false;
            }

            $dependencies = array_intersect($attribute->disabledWith, array_keys($checkedFields));

            return count($dependencies) == count($attribute->disabledWith);
            
        }, ARRAY_FILTER_USE_BOTH);

        if(!$currentCheck) {

            return;
        }

        foreach ($currentCheck as $propertyName => $attribute) {

            if($this->evaluateBoolOrExpression($attribute->disabled, $dto, $constraint)) {

                $dto->disableProperty($propertyName);

            } 
            
            $checkedFields[$propertyName] = $attribute;

        }

        $this->checkDisabledProperties($dto, $constraint, $checkedFields);

    }

    function checkNullableProperties(BaseDto $dto, DtoPayload $constraint) {

        foreach ($dto->getInitializedProperties() as $property => $attribute) {

            try {

                if(!$this->evaluateBoolOrExpression($attribute->nullable, $dto, $constraint)) {

                    $violations = $this->validator->validate($attribute->getValue(), new NotNull());

                    $this->checkViolations($violations, $property);
                }

            } catch(DtoFieldValidationException $e) {

                $dto->addPropertyErrors($e);

            }

        }

    }

    private function checkRequiredFields(BaseDto $dto, DtoPayload $constraint): void {

        foreach ($dto->getEnabledProperties() as $propertyName => $attribute) {

            try {

                if($this->evaluateBoolOrExpression($attribute->required, $dto, $constraint)) {

                    if(!$attribute->isInitialized()) {

                        throw new DtoFieldValidationException(
                            $propertyName, 
                            'this-value-should-not-be-blank'
                        );
                    }
            
                    $violations = $this->validator->validate($attribute->getValue(), new NotBlank(allowNull: true));
            
                    $this->checkViolations($violations, $propertyName);

                }

            } catch(DtoFieldValidationException $e) {
                
                $dto->addPropertyErrors($e);

            }

        }

    }

    private function applyConstraints(BaseDto $dto, DtoPayload $constraint): void {
        
        foreach ($dto->getInitializedProperties() as $property => $attribute) {

            try {

                $constraints = array_map(function($c) use ($dto) {

                    if($c instanceof AbstractComparison && !!($path = $c->propertyPath)) {

                        $c->propertyPath = null;

                        $c->value = $dto->$path->getValue();
                    }

                    return $c;

                }, $attribute->constraints);

                $violations = $this->validator->validate($attribute->getValue(), $constraints);

                $this->checkViolations($violations, $property);

            } catch(DtoFieldValidationException $e) {
    
                $dto->addPropertyErrors($e);

            }

        }
    }

    private function checkRawValueType(string $property, DtoField $attribute): mixed {

        if(in_array(DtoFieldType::ARRAY, $attribute->types)) {

            $values = $attribute->getRawValue();

            $violations = $this->validator->validate($values, new Type(DtoFieldType::ARRAY->value));

            $this->checkViolations($violations, $property);

            $itemTypes = array_filter($attribute->types, fn($t) => $t != DtoFieldType::ARRAY);

            foreach ($values as $item) {

                $violations->addAll($this->validator->validate($item, new Type(array_map(fn(DtoFieldType $t) => $t->value, $itemTypes))));

            }

            $this->checkViolations($violations, $property);

            return $values;

        } else {

            $value = $attribute->getRawValue();

            $violations = $this->validator->validate($value, new Type(array_map(fn(DtoFieldType $t) => $t->value, $attribute->types)));

            $this->checkViolations($violations, $property);

            return $value;

        }
            
    }

    private function evaluateBoolOrExpression(string | bool $value, BaseDto $dto, DtoPayload $constraint): bool {

        if(is_string($value)) {

            $expression = new ExpressionLanguage();

            $payload = $dto->getValues();

            $subject = $constraint->getSubject();

            $onUpdate = !!$subject ? true  : false;

            $onCreate = !!$subject ? false : true;

            $expression->register('willBe', function (string $field, array $values) { 

                $sValue = join(", ", array_map(function($v) {

                    if(is_string($v)) {

                        return "\"$v\"";
                    }

                    if(is_integer($v)) {

                        return $v;
                    }

                    throw new UnexpectedValueException($v, "string | integer");

                }, $values));

                return sprintf('willBe(%s)', "[ $sValue ]");
            
            }, function($arguments, $field, $values) use ($payload, $onUpdate, $subject) { 

                $method = s($field)->camel()->title();
                
                return ($payload->hasKey($field) && in_array($payload->get($field), $values)) || ($onUpdate && !$payload->hasKey($field) && in_array($subject->{"get$method"}(), $values)); 
            
            });

            return $expression->evaluate($value, [ 
                'onCreate' => $onCreate,
                'onUpdate' => $onUpdate,
                'subject'  => $subject,
                'payload'  => $payload
            ]);

        }

        return $value;

    }

    private function checkViolations(ConstraintViolationListInterface $violations, string $propertyName) {

        if($violations->count()) {

            $messages = [];

            foreach ($violations as $v) {

                $messages[] = $v->getMessage();
                
            }

            throw new DtoFieldValidationException(
                $propertyName, 
                $messages
            );
        }

    }


}