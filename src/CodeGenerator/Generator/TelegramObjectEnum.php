<?php

namespace TelegramApiParser\CodeGenerator\Generator;

enum TelegramObjectEnum
{
    case TYPE;
    case METHOD;

    public function interfaceClassName(): string {
        return match ($this) {
            self::TYPE => 'TelegramTypeInterface',
            self::METHOD => 'TelegramMethodInterface',
        };
    }

    public function extendsClassName(): string {
        return match ($this) {
            self::TYPE => 'TelegramType',
            self::METHOD => 'TelegramMethod',
        };
    }

    public function directory(): string {
        return match ($this) {
            self::TYPE => 'Types',
            self::METHOD => 'Methods',
        };
    }
}
