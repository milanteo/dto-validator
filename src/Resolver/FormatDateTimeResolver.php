<?php

namespace Teoalboo\DtoValidator\Resolver;

use DateTime;

class FormatDateTimeResolver extends DtoResolver {

    public function resolve(mixed $resolver, string $propertyName, mixed $dtoValue): mixed {

        if(is_array($dtoValue)) {

            $processed = array_map(fn(string $v) => DateTime::createFromFormat($resolver->format, $v), $dtoValue);

            if(array_any($processed, fn($v) => !$v instanceof DateTime)) {

                $this->throwError($propertyName, 'one-or-more-of-the-given-values-is-invalid');
            }

            return $processed;

        }

        $processed = DateTime::createFromFormat($resolver->format, $dtoValue);

        if(!$processed instanceof DateTime) {

            $this->throwError($propertyName, 'this-value-is-not-a-valid-datetime');
        }

        return $processed;

    }

}