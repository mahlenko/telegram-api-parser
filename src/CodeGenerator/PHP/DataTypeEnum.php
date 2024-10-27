<?php

namespace TelegramApiParser\CodeGenerator\PHP;

enum DataTypeEnum {
    case TYPE;
    case METHOD;

    public function toString() : string {
        return match ($this) {
            self::TYPE => 'Type',
            self::METHOD => 'Method',
        };
    }
}
