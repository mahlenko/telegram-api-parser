<?php

namespace TelegramApiParser\CodeGenerator\PHP;

class TypeGenerator {
    private const PHP_TYPE_MAPPING = [
        'boolean' => 'bool',
        'integer' => 'int',
        'int' => 'int',
        'float' => 'float',
        'double' => 'float',
        'string' => 'string',
        'array' => 'array',
        'true' => 'true',
        'false' => 'false'
    ];

    /**
     * Returns a string with a description of the types for DocBlock
     *
     * Example: InlineKeyboardButton[][] or array<InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo>
     *
     * @param  string|array  $types
     * @return string
     */
    public function toStringDocBlock(string|array $types): string {
        $array = $this->mapping($types);

        if (is_string($types)) {
            return implode('|', $array);
        }

        if (count($array) === count($array, COUNT_RECURSIVE)) {
            if (count($array) == 1) {
                return sprintf('%s[]', $array[0]);
            }

            return sprintf('array<%s>', implode('|', $array));
        }

        $multiArray = [];
        if (is_array($array[0])) {
            foreach ($array[0] as $key => $type) {
                if (is_array($type)) {
                    $multiArray[$key] = sprintf('%s[]', $this->toStringDocBlock($type));
                }
            }
        }

        return implode('|', $multiArray);
    }

    /**
     * Returns a string with types separated by "|"
     *
     * Example: InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|ForceReply
     *
     * @param  string|array  $types
     * @return string
     */
    public function toStringReturn(string|array $types): string {
        if (is_array($types))
            return 'array';

        $namespace = trim(PHPGenerator::NAMESPACE, '\\') . '\\' . DataTypeEnum::TYPE->toString();

        $array = $this->mapping($types);
        foreach ($array as $key => $type) {
            if (is_array($type)) {
                unset($array[$key]);
                continue;
            }

            if (!$this->isBaseType($type))
                $array[$key] = $namespace . '\\' . $type;
        }

        return implode('|', $array);
    }

    /**
     * Returns an array of custom types
     *
     * Example: [ TelegramBot\\Type\\Message, TelegramBot\\Type\\Update ]
     *
     * @param  string|array  $types
     * @return array
     */
    public function getDependenciesList(string|array $types): array {
        $result = [];
        $namespace = trim(PHPGenerator::NAMESPACE, '\\') . '\\' . DataTypeEnum::TYPE->toString();

        foreach ($this->flatten($this->mapping($types)) as $type) {
            if (!$this->isBaseType($type)) {
                $result[] = $namespace.'\\'.$type;
            }
        }

        return $result;
    }

    /**
     * Type mapping with PHP
     * @param  string|array  $types
     * @return string[]
     */
    private function mapping(string|array $types): array {
        if (is_string($types) && str_contains($types, ' or ')) {
            $types = explode(' or ', $types);
        }

        if (!is_array($types))
            return [ $this->mappingType($types) ];

        foreach ($types as $key => $type) {
            if (!is_array($type)) {
                $type = $this->mappingType($type);
            } else {
                foreach ($type as $k => $subType) {
                    $type[$k] = $this->mapping($subType);
                }
            }

            $types[$key] = $type;
        }

        return $types;
    }

    /**
     * @param  string  $type
     * @return string
     */
    private function mappingType(string $type): string {
        $lower = strtolower($type);

        if (key_exists($lower, self::PHP_TYPE_MAPPING)) {
            return self::PHP_TYPE_MAPPING[$lower];
        }

        return $type;
    }

    /**
     * @param  string  $type
     * @return bool
     */
    private function isBaseType(string $type): bool {
        return in_array($type, self::PHP_TYPE_MAPPING);
    }

    /**
     * @param  array  $array
     * @return array
     */
    private function flatten(array $array): array {
        $return = [];
        array_walk_recursive($array, function($arr) use (&$return) { $return[] = $arr; });
        return $return;
    }
}