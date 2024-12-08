<?php

namespace Teoalboo\DtoValidator\Processor;

use Teoalboo\DtoValidator\Resolver\FormatDateTimeResolver;

class FormatDateTime implements DtoProcessorInterface {

    public function __construct(
        public string $format = 'd-m-Y'
    ) { }

    public function resolvedBy(): string {

        return FormatDateTimeResolver::class;
    }

}