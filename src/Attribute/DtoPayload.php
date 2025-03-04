<?php

namespace Teoalboo\DtoValidator\Attribute;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

enum PayloadLocation {
    case Content;
    case Query;
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class DtoPayload {

    private mixed $subject;

    public function __construct(
        private int $errorCode = Response::HTTP_BAD_REQUEST,
        private ?PayloadLocation $location  = PayloadLocation::Content,
        ?string $subject = null
    ) {

        $this->subject = $subject;
    }

    public function getErrorCode(): int {

        return $this->errorCode;
    }

    public function getPayloadLocation(): PayloadLocation {

        return $this->location;
    }

    public function setSubject(mixed $subject): self {

        $this->subject = $subject;

        return $this;

    }

    public function getSubject(): mixed {

        return $this->subject;
    }

}
