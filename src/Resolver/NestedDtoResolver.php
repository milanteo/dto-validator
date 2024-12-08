<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Teoalboo\DtoValidator\Resolver\NestedDto;
use Teoalboo\DtoValidator\Validator\DtoPayload;
use stdClass;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NestedDtoResolver extends DtoResolver {

    public function __construct(
        private ValidatorInterface $validator
    ) { }

    public function resolve(mixed $resolver, string $propertyName, mixed $dtoValue): mixed {

        $errors = [];
        
        if(is_array($dtoValue)) {

            $valid = [];

            foreach ($dtoValue as $index => $item) {

                try {
        
                    $valid[] = $this->validateDtoInstance($resolver, $item);

        
                } catch(DtoPayloadValidationException $e) {
        
                    $errors["$propertyName.$index"] = $e->errors;
                    
                }

            }

        } else {

            try {

                $valid = $this->validateDtoInstance($resolver, $dtoValue);
        
            } catch(DtoPayloadValidationException $e) {
    
                array_push($errors, ...$e->errors);
                
            }

        }

        if($errors) {

            $this->throwError($propertyName, $errors);
        }

        return $valid;

    }

    public function validateDtoInstance(NestedDto $resolver, object $value) {

        if(is_array($resolver->config)) {

            $child = new stdClass();

            foreach (array_keys($resolver->config) as $key) {
                
                $child->$key = null;
            }

            $this->validator->validate($child, new DtoPayload(content: $value, fields: $resolver->config));

        } else {

            $child = new $resolver->config();
            
            $this->validator->validate($child, new DtoPayload(content: $value));

        }

        return $child;

    }

}