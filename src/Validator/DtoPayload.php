<?php

namespace Teoalboo\DtoValidator\Validator;

use Attribute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\ExpressionLanguage\Expression;

#[Attribute()]
class DtoPayload extends Constraint {

    private mixed $subject = null;

    #[HasNamedArguments]
    public function __construct(
        public int   $errorCode = Response::HTTP_BAD_REQUEST,
        array|string|Expression|null $subject = null
    ) {

        $this->subject = $subject;

        parent::__construct([], null, null);
    }

    public function setSubject(mixed $subject): self {

        $this->subject = $subject;

        return $this;

    }

    public function getSubject(): mixed {

        return $this->subject;
    }

}
