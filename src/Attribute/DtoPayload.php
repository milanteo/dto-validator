<?php

namespace Teoalboo\DtoValidator\Attribute;

use Attribute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ExpressionLanguage\Expression;

#[Attribute(Attribute::TARGET_PARAMETER)]
class DtoPayload {

    private mixed $subject = null;

    public function __construct(
        private int $errorCode = Response::HTTP_BAD_REQUEST,
        mixed $subject = null
    ) {

        $this->subject = $subject;

    }

    public function getErrorCode(): int {

        return $this->errorCode;
    }

    public function setSubject(mixed $subject): self {

        $this->subject = $subject;

        return $this;

    }

    public function getSubject(): mixed {

        return $this->subject;
    }

}
