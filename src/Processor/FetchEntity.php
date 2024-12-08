<?php

namespace Teoalboo\DtoValidator\Processor;

use Teoalboo\DtoValidator\Resolver\FetchEntityResolver;

class FetchEntity implements DtoProcessorInterface {

    public function __construct(
        public string $entity, 
        public string $identifier = 'id'
    ) { }

    public function resolvedBy(): string {

        return FetchEntityResolver::class;
    }

}