<?php
namespace Teoalboo\DtoValidator;

use Teoalboo\DtoValidator\Resolver\DtoResolverInterface;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DtoField {

    public function __construct(
        public array $types,
        public ?DtoResolverInterface $resolver = null,
        public array $constraints = [],
        public bool | string $nullable = false,
        public bool | string $required = false,
        public bool | string $disabled = false,
        public array $disabledWith = []
    ) { }

}