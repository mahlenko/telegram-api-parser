<?php

namespace TelegramApiParser\CodeGenerator\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\TraitType;
use RuntimeException;
use TelegramApiParser\CodeGenerator\GeneratorInterface;

class PHPGenerator implements GeneratorInterface
{
    private const NAMESPACE = 'TelegramBotCast';

    private string $output;

    public function __construct(string $output) {
        if (!file_exists($output)) mkdir($output, 0755);
        $this->output = realpath($output);
    }

    public function handle(string $file_source): void {
        if (!file_exists($file_source)) {
            throw new RuntimeException($file_source);
        }

        // get content parse documentation
        $content = file_get_contents($file_source);
        $json = json_decode($content);

        //
        $this->execute($json->documentation);
    }

    private function execute(array|object $documentation) {
        $interfaces = [
            TelegramObjectEnum::TYPE->name => $this->makeInterface(TelegramObjectEnum::TYPE->interface()),
            TelegramObjectEnum::METHOD->name => $this->makeInterface(TelegramObjectEnum::METHOD->interface()),
        ];

        foreach ($interfaces as $interface) {
            $this->putFile($interface);
        }

        foreach ($documentation as $doc) {
            if (in_array($doc->name, self::EXCLUDE_BLOCK_NAMES)) continue;

            // creating telegram objects
            foreach ($doc->sections as $section) {
                $type = isset($section->response) ? TelegramObjectEnum::METHOD : TelegramObjectEnum::TYPE;

                $interface = $interfaces[$type->name];
                $namespace = new PhpNamespace(self::NAMESPACE .'\\'. $type->directory());
                $this->makeClass($section, $interface, $namespace);

                $this->putFile($namespace);
            }
        }
    }

    private function makeClass($section, PhpNamespace $interface, PhpNamespace $namespace = null): void {
        $classname = $this->makeClassName($section->name);
        $interface_namespace = $this->getClassNamespace($interface);

        $class = new ClassType($classname, $namespace);
        $class->addImplement($interface_namespace);
        $class->setReadOnly();
        $class->setFinal();

        $this->addComments($class, ucfirst($section->name), $section->description);

        if (!key_exists($classname, $namespace->getClasses())) {
            $namespace->addUse($interface_namespace);
            $namespace->add($class);
        }

        // add response type
        if (isset($section->response) && $section->response) {
            $response_type = $section->response[0];

            if (str_contains($response_type, '[]')) {
                $clear_type = str_replace('[]', '', $response_type);
                if (strncmp(ucfirst($response_type), $response_type, 1) === 0) {
                    $type_namespace = self::NAMESPACE . '\\' . TelegramObjectEnum::TYPE->directory() . '\\' . $clear_type;
                    $namespace->addUse($type_namespace);

                    $clear_type = new Literal($clear_type.'::class');
                }

                $response_type = [ $clear_type ];
            } else {
                if (strncmp(ucfirst($response_type), $response_type, 1) === 0) {
                    $type_namespace = self::NAMESPACE . '\\' . TelegramObjectEnum::TYPE->directory() . '\\' . $response_type;
                    $namespace->addUse($type_namespace);

                    $response_type = new Literal($response_type.'::class');
                }
            }

            $class->addConstant('RESPONSE_TYPE', $response_type);
        }

        // Add method __construct
        if ($section->params) {
            $method = $class->addMethod('__construct');

            foreach ($section->params as $param) {
                try {
                    $parameter = $method->addPromotedParameter($param->name);
                    $parameter->addComment($param->description);
                    $parameter->setPublic();

                    $types = is_array($param->type) ? $param->type : [$param->type];
                    foreach ($types as $index => $type) {
                        if (str_contains($type, '[]')) {
                            $clear_type = str_replace('[]', '', $type);
                            $parameter->addComment('@var array<' . $clear_type . '>');
                            $types[$index] = 'array';

                            $type_namespace = self::NAMESPACE .'\\'. TelegramObjectEnum::TYPE->directory() .'\\'. $clear_type;

                            if ($section->name != $clear_type) {
                                $namespace->addUse($type_namespace);
                            }

                        } else {
                            if (strncmp(ucfirst($type), $type, 1) === 0) {
                                $types[$index] = self::NAMESPACE . '\\' . TelegramObjectEnum::TYPE->directory() . '\\' . $type;
                                if ($section->name != $type) {
                                    $namespace->addUse($types[$index]);
                                }
                            }
                        }
                    }

                    $types = array_unique($types);
                    $parameter->setType(implode('|', $types));
                    if (isset($param->optional)) $parameter->setNullable(!$param->optional);
                    if (isset($param->required)) $parameter->setNullable(!$param->required);

                } catch (\Exception $exception) {}
            }
        }
    }

    private function makeInterface(string $name, string $comment = null): PhpNamespace {
        $classname = $this->makeClassName($name);

        $interface = new InterfaceType($classname);
        $this->addComments($interface, $name, $comment);

        $namespace = new PhpNamespace(self::NAMESPACE .'\\Interface');
        $namespace->add($interface);

        return $namespace;
    }

    private function makeClassName(string $name): string {
        $name_chunks = explode(' ', $name);

        foreach ($name_chunks as $index => $name_chunk) {
            $name_chunks[$index] = ucfirst($name_chunk);
        }

        $name = implode('', $name_chunks);

        return $name;
    }

    private function addComments(
        ClassType|InterfaceType|TraitType|EnumType $class,
        string $name,
        string $comment = null
    ): void {
        $class->addComment($name);
        if ($comment) {
            $class->addComment('');
            $class->addComment($comment);
        }

        $class->addComment('');
        $class->addComment('@package Telegram Bot Cast');
        $class->addComment('@author Sergey Makhlenko <https://t.me/SergeyMakhlenko>');
        $class->addComment('@license https://mit-license.org/license.txt The MIT License (MIT)');
    }

    private function getFilename(PhpNamespace $namespace): ?string {
        foreach ($namespace->getClasses() as $class) {
            $directory = str_replace(
                self::NAMESPACE,
                '',
                str_replace('\\', DIRECTORY_SEPARATOR, $namespace->getName())
            );

            return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $class->getName() .'.php';
        }

        return null;
    }

    private function getNamespaceName(PhpNamespace $namespace) {
        $filename = $this->getFilename($namespace);
        $directory = rtrim(str_replace(basename($filename), '', $filename), DIRECTORY_SEPARATOR);
        return self::NAMESPACE . str_replace(DIRECTORY_SEPARATOR, '\\', $directory);
    }

    private function getClassNamespace(PhpNamespace $namespace): ?string {
        $namespace_name = $this->getNamespaceName($namespace);
        foreach ($namespace->getClasses() as $class) {
            return $namespace_name .'\\'. $class->getName();
        }

        return null;
    }

    private function putFile(PhpNamespace $namespace): void {
        $printer = new Printer;

        $filepath = $this->output . $this->getFilename($namespace);
        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = '<?php' . PHP_EOL . $printer->printNamespace($namespace);

        file_put_contents($filepath, $content);
    }

//    private function getVersion(): string {
//        return 'Bot API '. $this->API_version .' (release: '. $this->API_date->format('Y-m-d') .')';
//    }

}