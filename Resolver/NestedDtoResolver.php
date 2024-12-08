<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Teoalboo\DtoValidator\Processor\NestedDto;
use Teoalboo\DtoValidator\Validator\DtoPayload;
use stdClass;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NestedDtoResolver extends DtoProcessorResolver {

    public function __construct(
        private ValidatorInterface $validator
    ) { }

    public function process(mixed $processor, string $propertyName, mixed $dtoValue): mixed {

        $errors = [];
        
        if(is_array($dtoValue)) {

            $valid = [];

            foreach ($dtoValue as $index => $item) {

                try {
        
                    $valid[] = $this->validateDtoInstance($processor, $item);

        
                } catch(DtoPayloadValidationException $e) {
        
                    $errors["$propertyName.$index"] = $e->errors;
                    
                }

            }

        } else {

            try {

                $valid = $this->validateDtoInstance($processor, $dtoValue);
        
            } catch(DtoPayloadValidationException $e) {
    
                array_push($errors, ...$e->errors);
                
            }

        }

        if($errors) {

            $this->throwError($propertyName, $errors);
        }

        return $valid;

    }

    public function validateDtoInstance(NestedDto $processor, object $value) {

        if(is_array($processor->config)) {

            $child = new stdClass();

            foreach (array_keys($processor->config) as $key) {
                
                $child->$key = null;
            }

            $this->validator->validate($child, new DtoPayload(content: $value, fields: $processor->config));

        } else {

            $child = new $processor->config();
            
            $this->validator->validate($child, new DtoPayload(content: $value));

        }

        return $child;

    }

}