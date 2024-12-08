<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;

class DtoProcessorResolver {

    public function process(mixed $processor, string $propertyName, mixed $dtoValue): mixed {

        return $dtoValue;
    }

    public function throwError(string $propertyName, string | array $errors): void {

        throw new DtoFieldValidationException(
            $propertyName, 
            $errors
        );

    }

}