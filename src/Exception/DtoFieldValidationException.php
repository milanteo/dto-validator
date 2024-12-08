<?php

namespace Teoalboo\DtoValidator\Exception;

use Exception;

class DtoFieldValidationException extends Exception {

    public function __construct(
        private string $propertyPath,
        private array | string $messages
    ) {

        parent::__construct();
    }

    public function getPropertyPath(): string {

        return $this->propertyPath;
    }

    public function getMessages(): array {

        return is_array($this->messages) ? $this->messages : [ $this->messages ];
    }

}