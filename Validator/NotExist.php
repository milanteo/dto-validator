<?php

namespace Teoalboo\DtoValidator\Validator;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Attribute()]
class NotExist extends Constraint {

    #[HasNamedArguments]
    public function __construct(
        public string $entity, 
        public string $identifier = 'id'
    ) {

        parent::__construct([]);
    }

}
