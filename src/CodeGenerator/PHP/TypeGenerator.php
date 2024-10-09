<?php

namespace TelegramApiParser\CodeGenerator\PHP;

class TypeGenerator {
    private const BASE_TYPES = [
        'boolean' => 'bool',
        'integer' => 'int',
        'int' => 'int',
        'float' => 'float',
        'double' => 'float',
        'string' => 'string',
        'array' => 'array',
        'true' => 'true',
        'false' => 'false',
        'resource' => 'resource'
    ];

    public function getType(string|array $types, DataTypeEnum $type): string {
        $namespace = trim(PHPGenerator::NAMESPACE, '\\') . '\\' . $type->toString();
        $array = $this->toArray($types);

        if (!$this->hasArrays($array)) {
            foreach ($array as $key => $type) {
                if (!$this->isPHP($type))
                    $array[$key] = $namespace . '\\' . $type;
            }
            return implode('|', $array);
        }

        return 'array';
    }

    public function excludeDefaultTypes(string $classname, array $types, DataTypeEnum $type): array {
        $namespace = trim(PHPGenerator::NAMESPACE, '\\') .'\\'. $type->toString();

        $types = array_filter($types, function($type) use ($classname, $namespace) {
            return !in_array($type, self::BASE_TYPES) && $namespace . $classname !== $type;
        });

        if (!$types) return [];

        return $types;
    }

    public function toString(string|array $types, bool $nullable = false): string {
        $array = $this->toArray($types);
        return implode('|', $array);
    }

    public function toStringArray(array $array): string {
        $array = $this->toArray($array);

        if (count($array) === count($array, COUNT_RECURSIVE)) {
            return sprintf('array<%s>', implode('|', $array));
        }

        $multiArray = [];
        if (is_array($array[0])) {
            foreach ($array[0] as $key => $type) {
                if (is_array($type)) {
                    $multiArray[$key] = sprintf('array<%s>', $this->toStringArray($type));
                }
            }
        }

        return implode('|', $multiArray);
    }

    private function toArray(string|array $types): array {
        if (is_string($types) && str_contains($types, ' or ')) {
            $types = explode(' or ', $types);
        }

        if (!is_array($types))
            return [ $this->toPHP($types) ];

        foreach ($types as $key => $type) {
            if (!is_array($type)) {
                $type = $this->toPHP($type);
            } else {
                foreach ($type as $k => $subType) {
                    $type[$k] = $this->toArray($subType);
                }
            }

            $types[$key] = $type;
        }

        return $types;
    }

    private function toPHP(string $type): string {
        $lower = strtolower($type);

        if (key_exists($lower, self::BASE_TYPES)) {
            return self::BASE_TYPES[$lower];
        }

        return $type;
    }

    private function isPHP(string $type): bool {
        return in_array($type, self::BASE_TYPES);
    }

    private function hasArrays(array $arr): bool {
        foreach ($arr as $value) {
            if (is_array($value)) return true;
        }

        return false;
    }
}