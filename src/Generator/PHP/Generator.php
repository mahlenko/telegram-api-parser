<?php

namespace TelegramApiParser\Generator\PHP;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property as PhpProperty;
use TelegramApiParser\Exceptions\GeneratorException;
use TelegramApiParser\Generator\GeneratorLibraryInterface;
use TelegramApiParser\Helpers;

class Generator implements GeneratorLibraryInterface
{
    private Helpers $helper;

    const BASE_TYPES = ['string', 'float', 'int', 'bool', 'array', 'object'];

    /**
     * @throws GeneratorException
     */
    public function run(string $filename, string $package_version): void
    {
        if (!file_exists($filename)) {
            throw new GeneratorException('Please run `php console telegram:json`.');
        }

        $version = preg_replace(
            '/[^0-9.]/',
            '',
            str_replace('.json', '', basename($filename))
        );

        $this->helper = new Helpers($version);

        $json = json_decode(file_get_contents($filename));

        if (json_last_error()) {
            throw new GeneratorException('Parse JSON: '. json_last_error_msg());
        }

        $this->placeholders();

        foreach ($json->items as $item) {
            $this->create($item, [
                '@version' => 'Telegram Bot Casts v'.$package_version.' ('. $json->version. ')',
                '@author' => 'Sergey Makhlenko <https://github.com/mahlenko>',
            ]);
        }
    }

    /**
     * @param $item
     * @param array $comments
     * @return void
     */
    private function create($item, array $comments = []): void
    {
        /* Create interfaces */
        $namespace = $this->helper->namespace(PhpPaths::Interface->name);
        $interface = $this->interfaceBuild($namespace, $item, $comments);

        foreach($item->data as $method) {
            /* Telegram type */
            if (isset($method->data[0]->field) || in_array($item->name, ['Available types'])) {
                $this->typesBuild($method, $interface, $comments);
            } elseif (isset($method->data[0]->parameter) || in_array($item->name, ['Getting updates', 'Available methods'])) {
                /* Telegram method */
                $this->methodsBuild($method, $interface, $comments);
            } else {
                $this->typesBuild($method, $interface, $comments);
            }
        }
    }

    /**
     * @param PhpNamespace $namespace
     * @param $item
     * @param array $comments
     * @return string
     */
    private function interfaceBuild(PhpNamespace $namespace, $item, array $comments = []): string
    {
        $name = $this->helper->className($item->name) . PhpPaths::Interface->name;

        $interface = new InterfaceType($name);
        $interface->addComment($item->name . PHP_EOL);
        $interface->addComment($this->helper->wordwrap($item->description) . PHP_EOL);

        if ($comments) {
            foreach ($comments as $key => $value) {
                $interface->addComment($key .' '. $value);
            }

            $interface->addComment('');
        }

        $namespace->add($interface);

        $this->helper->save($namespace, $name);

        return $interface->getName();
    }

    /**
     * @return void
     */
    private function placeholders(): void
    {
        $namespace = $this->helper->namespace();
        $namespace->add(new ClassType('TelegramMethod'));

        $this->helper->save($namespace, 'TelegramMethod');
        $namespace->removeClass('TelegramMethod');

        $namespace->add(new ClassType('TelegramType'));
        $this->helper->save($namespace, 'TelegramType');
    }

    /**
     * @param object $type
     * @param string $interface
     * @param array $comments
     * @return void
     */
    private function typesBuild(object $type, string $interface, array $comments = []): void
    {
        if (str_contains($type->name, ' ')) return;

        $interface = $this->helper->pathFromBaseNamespace(PhpPaths::Interface->name .'/'. $interface);

        /* Create class */
        $class = new ClassType(ucfirst($type->name));
        $class->addComment($this->helper->wordwrap($type->description));
        $class->setExtends($this->helper->pathFromBaseNamespace('TelegramType'));
        $class->addImplement($interface);

        $class->setFinal();

        $class->addComment('');
        foreach ($comments as $key => $comment) {
            $comment = is_string($key) ? $key .' '. $comment : $comment;
            $class->addComment($comment);
        }

        /* Create namespace */
        $namespace = $this->helper->namespace(PhpPaths::Types->name);
        $namespace->addUse($interface);
        $namespace->addUse($this->helper->pathFromBaseNamespace('TelegramType'));

        /* Add properties */
        $propertyBuilder = new Property($namespace);
        $properties = [];
        foreach ($type->data as $item) {
            $properties[] = $propertyBuilder->handle(
                $item->field,
                $item->type,
                $item->description,
                !str_contains($item->description, 'Optional')
            );
        }

        $class->setProperties($properties);

        $namespace->add($class);

        $this->helper->save($namespace, $class->getName());
    }

    /**
     * @param object $method
     * @param string $interface
     * @param array $comments
     * @return void
     */
    private function methodsBuild(object $method, string $interface, array $comments = []): void
    {
        if (str_contains($method->name, ' ')) return;

        $interface = $this->helper->pathFromBaseNamespace(PhpPaths::Interface->name .'/'. $interface);

        /* Create class */
        $class = new ClassType(ucfirst($method->name));
        $class->setComment($this->helper->wordwrap($method->description));
        $class->setExtends($this->helper->pathFromBaseNamespace('TelegramMethod'));
        $class->addImplement($interface);
        $class->setFinal();

        $class->addComment('');
        foreach ($comments as $key => $comment) {
            $comment = is_string($key) ? $key .' '. $comment : $comment;
            $class->addComment($comment);
        }

        /* Create namespace */
        $namespace = $this->helper->namespace(PhpPaths::Methods->name);
        $namespace->addUse($this->helper->pathFromBaseNamespace('TelegramMethod'));
        $namespace->addUse($interface);

        /* Add properties */
        $properties = [];
        $required_properties = [];

        $propertyBuilder = new Property($namespace);
        foreach ($method->data as $item) {
            $required = $item->required !== 'Optional';
            if ($required) $required_properties[] = $item->parameter;

            /* Add property */
            $property = $propertyBuilder->handle(
                $item->parameter,
                $item->type,
                $item->description,
                $required
            );

            // add user type
            foreach ($property->getType(true)->getTypes() as $type) {
                if (str_contains($type, $_ENV['BASE_NAMESPACE'])) {
                    $namespace->addUse($type);
                }
            }

            $properties[] = $property;
        }

        /* Create list required properties */
        $properties[] = $propertyBuilder->handle(
            'required_properties',
            'array',
            'A list of necessary properties that should be checked before sending requests to the Telegram Bot API',
            true
        )->setValue($required_properties);

        // response types
        if (isset($method->response)) {
            $types = $this->makeResponseTypes($method->response);

            $response = new PhpProperty('response_type');
            $response->addComment('Response mapping type.');

            $type = $types['types'];
            if (is_array($type)) {
                $response->setType('array');
                $response->setValue([new Literal($type[0] .'::class')]);
                $response->addComment('@var array<'. $type[0] .'>');
            } else {
                $response->setType('string');

                if (in_array($type, self::BASE_TYPES)) {
                    $response->setValue($type);
                } else {
                    $response->setValue(new Literal($type .'::class'));
                }
            }

            if ($types['use']) {
                foreach ($types['use'] as $use) {
                    $namespace->addUse($use);
                }
            }

//            $class->addProperty('qweqeqe', new Literal($types['types'][0]));

            $properties[] = $response;
        }

        $class->setProperties($properties);

        $namespace->add($class);

        $this->helper->save($namespace, ucfirst($method->name));
    }

    private function makeResponseTypes(array $types): array {
        $typeObjectNamespace = $_ENV['BASE_NAMESPACE'] .'Types\\';
        $baseTypes = self::BASE_TYPES;

        $result = [
            'types' => [],
            'use' => [],
        ];

        foreach ($types as $type) {
            if (str_contains($type, 'array<')) {
                preg_match('/array<(.*)>/', $type, $matches);
                if (!key_exists(1, $matches)) continue;

                $type = [ $matches[1] ];
                $result['comment'][] = '@var array<'.$matches[1].'>';
                $result['use'][] = $typeObjectNamespace . $matches[1];
            }

            if (is_string($type) && !in_array($type, $baseTypes)) {
                $result['use'][] = $typeObjectNamespace . $type;
            }

            $type = match ($type) {
                true, false => 'bool',
                default => $type
            };

            $result['types'] = $type;
        }

        return $result;
    }
}