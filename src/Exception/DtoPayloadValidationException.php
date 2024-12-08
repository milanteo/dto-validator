<?php

namespace Teoalboo\DtoValidator\Exception;

use Exception;

class DtoPayloadValidationException extends Exception {

    public function __construct(
        public array $errors,
        public int   $errorCode
    ) {

        parent::__construct();
    }

}