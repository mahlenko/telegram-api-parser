<?php

namespace TelegramApiParser\Generator\PHP;

use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property as PhpProperty;
use TelegramApiParser\Helpers;

class Property
{
    const GLOBAL_VARIABLES_TYPE = [
        'boolean' => 'bool',
        'integer' => 'int',
        'double' => 'float',
        'float' => 'float',
        'string' => 'string',
        'array' => 'array',
        'object' => 'object',
        'resource' => 'resource'
    ];

    const SPECIFIED_TYPE = [
        'true' => 'bool',
        'false' => 'bool',
        'float number' => 'float',
    ];

    private PhpProperty $property;

    private PhpNamespace $namespace;

    public function __construct(PhpNamespace $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param string $key
     * @param string $type
     * @param string|null $comment
     * @param bool|null $required
     * @return PhpProperty
     */
    public function handle(string $key, string $type, string $comment = null, bool $required = null): PhpProperty
    {
        $this->property = new PhpProperty($key);
        $this->property->setPublic();

        if (!empty($comment)) {
            $this->property->addComment(Helpers::wordwrap($comment));
            $this->setType($type);

            if (is_null($required)) {
                $this->property->setNullable(str_contains($comment, 'Optional'));
            } else {
                $this->property->setNullable(!$required);
            }
        } else {
            $this->setType($type);
        }


        return $this->property;
    }

    private function setType(string $type): void
    {
        if ($simpleType = $this->simpleType($type)) {
            $this->property->setType($simpleType);
            return;
        }

        if (preg_match('/( of )/', $type)) {
            list($type, $dataType) = explode(' of ', $type);
            $this->property->setType(strtolower($type));

            if (str_contains($dataType, 'InputMedia')) {
                $dataType = 'InputMedia';
            }

            $dataTypeNamespace = Helpers::pathFromBaseNamespace('Types/'.$dataType);
            $this->namespace->addUse($dataTypeNamespace);

            $this->property->addComment(PHP_EOL .'@var array<'.$dataType.'>');

            return;
        }

        if (preg_match('/( or )/', $type)) {
            $types = explode(' or ', $type);
            foreach ($types as $index => $value) {
                $value = $this->simpleType($value) ?? Helpers::pathFromBaseNamespace(PhpPaths::Types->name .'/'. $value);
                $types[$index] = $value;
            }

            $this->property->setType(implode('|', $types));

            return;
        }

        $type = Helpers::pathFromBaseNamespace(PhpPaths::Types->name.'/'.$type);
        $this->property->setType($type);
    }

    private function simpleType(string $type): ?string
    {
        $lowerType = strtolower($type);

        if (key_exists($lowerType, self::GLOBAL_VARIABLES_TYPE)) {
            return self::GLOBAL_VARIABLES_TYPE[$lowerType];
        }

        if (key_exists($lowerType, self::SPECIFIED_TYPE)) {
            return self::SPECIFIED_TYPE[$lowerType];
        }

        return null;
    }

}