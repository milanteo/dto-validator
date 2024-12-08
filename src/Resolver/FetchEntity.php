<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Resolver\FetchEntityResolver;

class FetchEntity implements DtoResolverInterface {

    public function __construct(
        public string $entity, 
        public string $identifier = 'id'
    ) { }

    public function resolvedBy(): string {

        return FetchEntityResolver::class;
    }

}