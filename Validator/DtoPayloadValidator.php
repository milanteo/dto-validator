<?php

namespace Teoalboo\DtoValidator\Validator;

use Teoalboo\DtoValidator\DtoField;
use Teoalboo\DtoValidator\DtoFieldType;
use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;
use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Ds\Map;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use stdClass;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use function Symfony\Component\String\s;

class DtoPayloadValidator extends ConstraintValidator {

    public function __construct(
        private ContainerInterface $container,
        private RequestStack $request
    ) { }

    public function validate(mixed $dto, Constraint $constraint) {

        if (!is_object($dto)) {

            throw new UnexpectedValueException($dto, 'object');
        }

        if (!$constraint instanceof DtoPayload) {

            throw new UnexpectedValueException($constraint, DtoPayload::class);
        }

        //SECTION - Metodo non standard
        $this->context->setNode($dto, $dto, $this->context->getMetadata(), $this->context->getPropertyPath());

        $context = $this->context->getValidator()->inContext($this->context);
        // !SECTION

        $constraint->content = $this->initConstraintContent($constraint);

        $constraint->properties = $this->calcDtoProperties($constraint, $dto);

        $this->checkValues($context, $constraint, $dto);
        
        $this->checkDisabledProperties($context, $constraint, $dto);
        
        $this->checkNullableProperties($context, $constraint, $dto);
        
        $this->checkRequiredFields($context, $constraint, $dto);

        $this->applyConstraints($context, $constraint, $dto);

        $this->throwErrors($constraint);

    }

    private function initConstraintContent(DtoPayload $constraint): object {

        $content = $constraint->content ?? json_decode($this->request->getCurrentRequest()->getContent());

        return is_object($content) ? $content : new stdClass();
    }

    private function calcDtoProperties(DtoPayload $constraint, object $dto): array {

        if($constraint->fields) {

            return $constraint->fields;
        }

        $properties = [];

        $reflection = new ReflectionClass($dto);

        foreach ($reflection->getProperties() as $property) {

            if(count($attributes = $property->getAttributes(DtoField::class))) {

                [ $attribute ] = $attributes;

                $properties[$property->getName()] = $attribute->newInstance();

            }
            
        }

        return $properties;

    }

    private function checkValues(ContextualValidatorInterface $context, DtoPayload $constraint, object $dto) {
        
        foreach ($constraint->getEnabledParameters() as $property => $value) {

            try {

                $attribute = $constraint->getPropertyAttribute($property);

                $this->checkValueType($context, $property, $value, $attribute);

                if(is_null($value) || is_null($attribute->processor)) { 

                    $dto->$property = $value;
                    
                } else {
        
                    $resolver = $this->container->get($attribute->processor->resolvedBy());
                    
                    $dto->$property = $resolver->process($attribute->processor, $property, $value);
        
                }

            } catch(DtoFieldValidationException $e) {

                $constraint->addError($e);

            }

        }

    }

    private function checkValueType(ContextualValidatorInterface $context, string $property, mixed $value, DtoField $attribute): void {

        if(in_array(DtoFieldType::ARRAY, $attribute->types)) {

            $context
                ->atPath($property)
                ->validate($value, new Type(DtoFieldType::ARRAY->value))
            ;

            $this->checkViolations($context, $property);

            $itemTypes = array_filter($attribute->types, fn($t) => $t != DtoFieldType::ARRAY);

            foreach ($value as $arrayValue) {

                $context
                    ->atPath($property)
                    ->validate($arrayValue, new Type(array_map(fn(DtoFieldType $t) => $t->value, $itemTypes)))
                ;

            }

            $this->checkViolations($context, $property);

        } else {

            $context
                ->atPath($property)
                ->validate($value, new Type(array_map(fn(DtoFieldType $t) => $t->value, $attribute->types)))
            ;

            $this->checkViolations($context, $property);

        }
            
    }

    private function checkViolations(ContextualValidatorInterface $context, string $propertyName) {

        if($context->getViolations()->count()) {

            $violations = [];

            foreach ($context->getViolations() as $index => $v) {

                $violations[] = $v->getMessage();
                
                $context->getViolations()->remove($index);
            }

            throw new DtoFieldValidationException(
                $propertyName, 
                $violations
            );
        }

    }

    private function checkDisabledProperties(ContextualValidatorInterface $context, DtoPayload $constraint, object $dto, array &$checkedFields = []): void {

        $currentCheck = array_filter($constraint->getEnabledProperties(includeErrorProperties: true), function($attribute, $propertyName) use (&$checkedFields) {

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

            if($this->evaluateBoolOrExpression($constraint, $attribute->disabled, $dto)) {

                $toDisable = array_filter($constraint->getEnabledProperties(includeErrorProperties: true), fn($att) => in_array($propertyName, $att->disabledWith));

                // Viene disabilitata la proprietà stessa e tutte quelle che ne dipendono
                $constraint->disableProperties([ $propertyName => $attribute, ...$toDisable ]);

            } 
            
            $checkedFields[$propertyName] = $attribute;

        }

        $this->checkDisabledProperties($context, $constraint, $dto, $checkedFields);

    }

    function checkNullableProperties(ContextualValidatorInterface $context, DtoPayload $constraint, object $dto) {

        foreach (array_keys($constraint->getEnabledParameters()) as $property) {

            try {

                $attribute = $constraint->getPropertyAttribute($property);

                if(!$this->evaluateBoolOrExpression($constraint, $attribute->nullable, $dto)) {

                    $context->atPath($property)->validate($dto->$property, new NotNull());

                    $this->checkViolations($context, $property);
                }

            } catch(DtoFieldValidationException $e) {

                $constraint->addError($e);

            }

        }


    }

    private function checkRequiredFields(ContextualValidatorInterface $context, DtoPayload $constraint, object $dto): void {

        foreach ($constraint->getEnabledProperties() as $propertyName => $attribute) {

            try {

                if($this->evaluateBoolOrExpression($constraint, $attribute->required, $dto)) {

                    if(!in_array($propertyName, array_keys($constraint->getEnabledParameters()))) {

                        throw new DtoFieldValidationException(
                            $propertyName, 
                            'this-value-should-not-be-blank'
                        );
                    }
            
                    $context->atPath($propertyName)->validate($dto->$propertyName, new NotBlank(allowNull: true));
            
                    $this->checkViolations($context, $propertyName);

                }

            } catch(DtoFieldValidationException $e) {
                
                $constraint->addError($e);

            }

        }

    }

    private function evaluateBoolOrExpression(DtoPayload $constraint, string | bool $value, object $dto): bool {

        if(is_string($value)) {

            $expression = new ExpressionLanguage();

            $payload = new Map($constraint->getPropertiesValues($dto));

            $subject = is_object($constraint->subject) ? $constraint->subject : null;

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

    private function applyConstraints(ContextualValidatorInterface $context, DtoPayload $constraint, object $dto) {
        
        foreach (array_keys($constraint->getEnabledParameters()) as $property) {

            try {

                $attribute = $constraint->getPropertyAttribute($property);
                
                $context
                    ->atPath($property)
                    ->validate($dto->$property, $attribute->constraints)
                ;

                $this->checkViolations($context, $property);

            } catch(DtoFieldValidationException $e) {
    
                $constraint->addError($e);

            }

        }

    }

    private function throwErrors(DtoPayload $constraint): void {

        if($errors = $constraint->getAllErrors()) {

            throw new DtoPayloadValidationException($errors, $constraint->errorCode);
        }

    }

}