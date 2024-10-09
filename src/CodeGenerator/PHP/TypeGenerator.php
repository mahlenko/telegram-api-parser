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
    ];

    public function getType(string|array $types, string $namespace = 'Type'): string {
        $namespace = trim(PHPGenerator::NAMESPACE, '\\') . '\\' . $namespace;
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

    public function toString(string|array $types, bool $nullable = false): string {
        $array = $this->toArray($types);

        $nullableType = $nullable ? '|null' : null;

        if (!$this->hasArrays($array))
            return implode('|', $array) . $nullableType;

        return $this->toStringArray($array[0]);
    }

    public function excludeDefaultTypes(string $classname, array $types, string $namespace = 'Type'): array {
        $namespace = trim(PHPGenerator::NAMESPACE, '\\') .'\\'. $namespace;

        $types = array_filter($types, function($type) use ($classname, $namespace) {
            return !in_array($type, self::BASE_TYPES) && $namespace . $classname !== $type;
        });

        if (!$types) return [];

        return $types;
    }

    private function toStringArray(string|array $array, string $result = ''): string {
        foreach ($array as $value) {
            if (is_array($value)) {
                $value = $this->toStringArray($value, $result);
            }

            $result = sprintf('array<%s>', $value);
        }

        return $result;
    }

    private function toArray(string|array $types): array {
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