<?php

namespace Teoalboo\DtoValidator\Resolver;

use DateTime;

class FormatDateTimeResolver extends DtoProcessorResolver {

    public function process(mixed $processor, string $propertyName, mixed $dtoValue): mixed {

        if(is_array($dtoValue)) {

            $processed = array_map(fn(string $v) => DateTime::createFromFormat($processor->format, $v), $dtoValue);

            if(array_any($processed, fn($v) => !$v instanceof DateTime)) {

                $this->throwError($propertyName, 'one-or-more-of-the-given-values-is-invalid');
            }

            return $processed;

        }

        $processed = DateTime::createFromFormat($processor->format, $dtoValue);

        if(!$processed instanceof DateTime) {

            $this->throwError($propertyName, 'this-value-is-not-a-valid-datetime');
        }

        return $processed;

    }

}