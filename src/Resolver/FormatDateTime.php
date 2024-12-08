<?php

namespace Teoalboo\DtoValidator\Resolver;

use Teoalboo\DtoValidator\Resolver\FormatDateTimeResolver;

class FormatDateTime implements DtoResolverInterface {

    public function __construct(
        public string $format = 'd-m-Y'
    ) { }

    public function resolvedBy(): string {

        return FormatDateTimeResolver::class;
    }

}