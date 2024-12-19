<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;

abstract class DtoResolver {

    abstract public function resolve(mixed $resolver, string $propertyName, mixed $dtoValue): mixed;

    public function throwError(string $propertyName, string | array $errors): void {

        throw new DtoFieldValidationException(
            $propertyName, 
            $errors
        );

    }

}