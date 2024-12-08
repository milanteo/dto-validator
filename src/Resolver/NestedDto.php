<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Resolver\NestedDtoResolver;

class NestedDto implements DtoResolverInterface {

    public function __construct(
        public array | string $config
    ) { }

    public function resolvedBy(): string {

        return NestedDtoResolver::class;
    }

}