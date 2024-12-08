<?php

namespace Teoalboo\DtoValidator;

enum DtoFieldType: string {
    case STRING  = 'string';
    case NUMBER  = 'number';
    case INTEGER = 'integer';
    case FLOAT   = 'float';
    case OBJECT  = 'object';
    case ARRAY   = 'array';
    case BOOLEAN = 'boolean';
}