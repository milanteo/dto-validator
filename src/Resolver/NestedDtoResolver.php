<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Teoalboo\DtoValidator\Resolver\NestedDto;
use Teoalboo\DtoValidator\Attribute\DtoPayload;
use Teoalboo\DtoValidator\BaseDto;
use Teoalboo\DtoValidator\Service\DtoValidatorService;

class NestedDtoResolver extends DtoResolver {

    public function __construct(
        private DtoValidatorService $validator
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

            $child = new BaseDto(properties: $resolver->config, content: $value);

            $this->validator->validate($child, new DtoPayload());

        } else {

            $child = new $resolver->config(content: $value);
            
            $this->validator->validate($child, new DtoPayload());

        }

        return $child;

    }

}