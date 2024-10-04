<?php

namespace TelegramApiParser\CodeGenerator\Generator;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Dto;

enum TelegramObjectEnum
{
    case TYPE;
    case METHOD;

    public function interfaceClassName(): string {
        return match ($this) {
            self::TYPE => 'TypeInterface',
            self::METHOD => 'MethodInterface',
        };
    }

    public function extendClass() {
        return match ($this) {
            self::TYPE => Dto::class,
            self::METHOD => Data::class,
        };
    }

    public function directory(): string {
        return match ($this) {
            self::TYPE => 'Type',
            self::METHOD => 'Method',
        };
    }
}
