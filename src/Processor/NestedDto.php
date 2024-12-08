<?php

namespace Teoalboo\DtoValidator\Processor;

use Teoalboo\DtoValidator\Resolver\NestedDtoResolver;

class NestedDto implements DtoProcessorInterface {

    public function __construct(
        public array | string $config
    ) { }

    public function resolvedBy(): string {

        return NestedDtoResolver::class;
    }

}