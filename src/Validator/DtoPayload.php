<?php

namespace Teoalboo\DtoValidator\Validator;

use Attribute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\ExpressionLanguage\Expression;

#[Attribute()]
class DtoPayload extends Constraint {

    #[HasNamedArguments]
    public function __construct(
        public int   $errorCode = Response::HTTP_BAD_REQUEST,
        public array|string|Expression|null $subject = null,
        ?array $groups = null, 
        $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }

}
