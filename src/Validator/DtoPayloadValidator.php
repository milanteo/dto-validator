<?php

namespace Teoalboo\DtoValidator\Validator;

use Teoalboo\DtoValidator\DtoField;
use Teoalboo\DtoValidator\DtoFieldType;
use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;
use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Ds\Map;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Teoalboo\DtoValidator\BaseDto;

use function Symfony\Component\String\s;

class DtoPayloadValidator extends ConstraintValidator {

    public function __construct(
        private ContainerInterface $container,
        private RequestStack $request
    ) { }

    public function validate($dto, Constraint $constraint): void {

        if (!$dto instanceof BaseDto) {

            throw new UnexpectedValueException($dto, BaseDto::class);
        }

        if (!$constraint instanceof DtoPayload) {

            throw new UnexpectedValueException($constraint, DtoPayload::class);
        }

        $validator = $this->context->getValidator();

        $this->checkValues($validator, $dto);
        
        $this->checkDisabledProperties($dto, $constraint);
        
        $this->checkNullableProperties($validator, $dto, $constraint);
        
        $this->checkRequiredFields($validator, $dto, $constraint);

        $this->applyConstraints($validator, $dto);

        $this->throwErrors($dto, $constraint);
        
    }

    private function throwErrors(BaseDto $dto, DtoPayload $constraint): void {

        if($errors = $dto->getAllErrors()) {

            throw new DtoPayloadValidationException($errors, $constraint->errorCode);
        }

    }

    function checkValues(ValidatorInterface $validator, BaseDto $dto) {
        
        foreach ($dto->getEnabledParameters() as $property => $attribute) {

            try {

                $this->checkValueType($validator, $property, $attribute);

                if(is_null($attribute->getValue()) || is_null($attribute->resolver)) { 

                    continue;
                } else {

                    $resolver = $this->container->get($attribute->resolver->resolvedBy());
                    
                    $attribute->setValue($resolver->resolve($attribute->resolver, $property, $attribute->getValue()));
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

    function checkNullableProperties(ValidatorInterface $validator, BaseDto $dto, DtoPayload $constraint) {

        foreach ($dto->getEnabledParameters() as $property => $attribute) {

            try {

                if(!$this->evaluateBoolOrExpression($attribute->nullable, $dto, $constraint)) {

                    $violations = $validator->validate($attribute->getValue(), new NotNull());

                    $this->checkViolations($violations, $property);
                }

            } catch(DtoFieldValidationException $e) {

                $dto->addPropertyErrors($e);

            }

        }

    }

    private function checkRequiredFields(ValidatorInterface $validator, BaseDto $dto, DtoPayload $constraint): void {

        foreach ($dto->getEnabledProperties() as $propertyName => $attribute) {

            try {

                if($this->evaluateBoolOrExpression($attribute->required, $dto, $constraint)) {

                    if(!in_array($propertyName, array_keys($dto->getEnabledParameters()))) {

                        throw new DtoFieldValidationException(
                            $propertyName, 
                            'this-value-should-not-be-blank'
                        );
                    }
            
                    $violations = $validator->validate($attribute->getValue(), new NotBlank(allowNull: true));
            
                    $this->checkViolations($violations, $propertyName);

                }

            } catch(DtoFieldValidationException $e) {
                
                $dto->addPropertyErrors($e);

            }

        }

    }

    private function applyConstraints(ValidatorInterface $validator, BaseDto $dto) {
        
        foreach ($dto->getEnabledParameters() as $property => $attribute) {

            try {

                $constraints = array_map(function($c) use ($dto) {

                    if($c instanceof AbstractComparison && !!($path = $c->propertyPath)) {

                        $c->propertyPath = null;

                        $c->value = $dto->$path->getValue();
                    }

                    return $c;

                }, $attribute->constraints);

                $violations = $validator->validate($attribute->getValue(), $constraints);

                $this->checkViolations($violations, $property);

            } catch(DtoFieldValidationException $e) {
    
                $dto->addPropertyErrors($e);

            }

        }
    }

    private function checkValueType(ValidatorInterface $validator, string $property, DtoField $attribute): void {

        if(in_array(DtoFieldType::ARRAY, $attribute->types)) {

            $violations = $validator->validate($attribute->getValue(), new Type(DtoFieldType::ARRAY->value));

            $this->checkViolations($violations, $property);

            $itemTypes = array_filter($attribute->types, fn($t) => $t != DtoFieldType::ARRAY);

            foreach ($attribute->getValue() as $arrayValue) {

                $violations->addAll($validator->validate($arrayValue, new Type(array_map(fn(DtoFieldType $t) => $t->value, $itemTypes))));

            }

            $this->checkViolations($violations, $property);

        } else {

            $violations = $validator->validate($attribute->getValue(), new Type(array_map(fn(DtoFieldType $t) => $t->value, $attribute->types)));

            $this->checkViolations($violations, $property);

        }
            
    }

    private function evaluateBoolOrExpression(string | bool $value, BaseDto $dto, DtoPayload $constraint): bool {

        if(is_string($value)) {

            $expression = new ExpressionLanguage();

            $payload = new Map($dto->getParametersValues());

            $subject = $constraint->subject;

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
