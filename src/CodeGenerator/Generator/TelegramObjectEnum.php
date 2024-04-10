<?php

namespace TelegramApiParser\CodeGenerator\Generator;

enum TelegramObjectEnum
{
    case TYPE;
    case METHOD;

    public function interface(): string {
        return match ($this) {
            self::TYPE => 'TelegramTypeInterface',
            self::METHOD => 'TelegramMethodInterface',
        };
    }

    public function directory(): string {
        return match ($this) {
            self::TYPE => 'Types',
            self::METHOD => 'Methods',
        };
    }
}
