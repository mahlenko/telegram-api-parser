<?php

namespace TelegramApiParser\Generator\PHP;

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

        $this->setType($type);

        if (!empty($comment)) {
            $this->property->setComment(Helpers::wordwrap($comment));
            if (is_null($required)) {
                $this->property->setNullable(str_contains($comment, 'Optional'));
            } else {
                $this->property->setNullable(!$required);
            }
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
            $this->property->addComment('@return array<'.$dataType.'>');

            return;
        }

        if (preg_match('/( or )/', $type)) {
            list($first, $second) = explode(' or ', $type);

            $firstType = $this->simpleType($first) ?? Helpers::pathFromBaseNamespace(PhpPaths::Types->name .'/'. $first);
            $secondType = $this->simpleType($second) ?? Helpers::pathFromBaseNamespace(PhpPaths::Types->name .'/'. $second);

            $this->property->setType($firstType . '|' . $secondType);

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