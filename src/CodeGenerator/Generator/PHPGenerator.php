<?php

namespace TelegramApiParser\CodeGenerator\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use RuntimeException;
use TelegramApiParser\CodeGenerator\GeneratorInterface;
use TelegramApiParser\CodeGenerator\Printer;
use TelegramApiParser\CodeGenerator\StringHelper;

class PHPGenerator implements GeneratorInterface
{
    private const NAMESPACE = 'TelegramBotCast';

    private const DefaultResponseName = 'ResponseObject';

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
        /* make interfaces */
        foreach (TelegramObjectEnum::cases() as $case) {
            $interface = $this->makeInterface($case);
            $this->putFile($interface);
        }

        /* Make ResponseObject */
        $this->makeResponseObject(self::DefaultResponseName);

        /* Make documentation */
        foreach ($documentation as $doc) {
            if (in_array($doc->name, self::EXCLUDE_BLOCK_NAMES)) continue;

            // creating telegram objects
            foreach ($doc->sections as $section) {
                $type = isset($section->response) ? TelegramObjectEnum::METHOD : TelegramObjectEnum::TYPE;

                $namespace = new PhpNamespace(self::NAMESPACE .'\\'. $type->directory());
                $this->makeClass($section, $namespace, $type);

                $this->putFile($namespace);
            }
        }
    }

    private function makeClass($section, PhpNamespace $namespace = null, TelegramObjectEnum $type = null): void {
        $classname = $this->makeClassName($section->name);

        $class = new ClassType($classname, $namespace);
        $class->setExtends($type->extendClass());
        $class->setFinal();

        if ($type->interfaceClassName()) {
            $interface = sprintf('\\%s\\Interface\\%s', self::NAMESPACE, $type->interfaceClassName());
            $namespace->addUse($interface);
            $class->addImplement($interface);
        }

        $this->addComments($class, ucfirst($section->name), $section->description);

        if (!key_exists($classname, $namespace->getClasses())) {
            $namespace->addUse($type->extendClass());
            $namespace->add($class);
        }

        // add response type
        if (isset($section->response) && $section->response) {
            $response_type = $section->response[0];

            if (str_contains($response_type, '[]')) {
                $clear_type = str_replace('[]', '', $response_type);
                if (strncmp(ucfirst($clear_type), $clear_type, 1) === 0) {
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

            if ($response_type == 'true') {
                $response_type = new Literal(self::DefaultResponseName.'::class');
                $namespace->addUse(self::NAMESPACE . '\\' . TelegramObjectEnum::TYPE->directory() . '\\' . self::DefaultResponseName);
            }

            $class->addConstant('RESPONSE_TYPE', $response_type);
        }

        // Add method __construct
        if ($section->params) {
            $method = $class->addMethod('__construct');

            foreach ($section->params as $param) {
                try {
                    $parameter = $method->addPromotedParameter($param->name);
                    $parameter->addComment(StringHelper::wrap($param->description, 70));
                    $parameter->setPublic();

                    $types = is_array($param->type) ? $param->type : [$param->type];
                    foreach ($types as $index => $type) {
                        if (str_contains($type, '[]')) {
                            $clear_type = str_replace('[]', '', $type);
                            $parameter->addComment('@var array<' . $clear_type . '>');
                            $types[$index] = 'array';

                            $type_namespace = self::NAMESPACE .'\\'. TelegramObjectEnum::TYPE->directory() .'\\'. $clear_type;

                            if ($section->name != $clear_type && strncmp(ucfirst($clear_type), $clear_type, 1) === 0) {
                                $namespace->addUse($type_namespace);
                            }

                        } else {
                            if (strncmp(ucfirst($type), $type, 1) === 0) {
                                $types[$index] = self::NAMESPACE . '\\' . TelegramObjectEnum::TYPE->directory() . '\\' . $type;
                                if ($section->name != $type && strncmp(ucfirst($type), $type, 1) === 0) {
                                    $namespace->addUse($types[$index]);
                                }
                            }
                        }
                    }

                    $types = array_unique($types);
                    $parameter->setType(implode('|', $types));
                    if (isset($param->optional)) $parameter->setNullable($param->optional);
                    if (isset($param->required)) $parameter->setNullable(!$param->required);

                } catch (\Exception $exception) {}
            }
        }
    }

    private function makeInterface(TelegramObjectEnum $case, string $comment = null): PhpNamespace {
        $classname = $this->makeClassName($case->interfaceClassName());

        $interface = new InterfaceType($classname);
        if ($classname == TelegramObjectEnum::METHOD->interfaceClassName()) {
            $interface->addMethod('toArray')
                ->setPublic()
                ->setReturnType('array');
        }
        $this->addComments($interface, $case->interfaceClassName(), $comment);

        $namespace = new PhpNamespace(self::NAMESPACE .'\\Interface');
        $namespace->add($interface);

        return $namespace;
    }

    private function makeResponseObject(string $name): ClassType {
        $type = TelegramObjectEnum::TYPE;
        $namespace = new PhpNamespace(self::NAMESPACE .'\\'. $type->directory());
        $namespace->addUse($type->extendClass());

        $class = new ClassType($name, $namespace);
        $class->setExtends($type->extendClass());
        $class->setFinal();

        if ($type->interfaceClassName()) {
            $interface = sprintf('\\%s\\Interface\\%s', self::NAMESPACE, $type->interfaceClassName());
            $namespace->addUse($interface);
            $class->addImplement($interface);
        }

        $method = $class->addMethod('__construct');

        $parameters = [
            'ok' => ['type' => ['bool'], 'nullable' => false],
            'result' => ['type' => ['bool', 'array'], 'nullable' => true],
            'error_code' => ['type' => ['int'], 'nullable' => true],
            'description' => ['type' => ['string'], 'nullable' => true],
        ];

        foreach ($parameters as $name => $options) {
            $method->addPromotedParameter($name)
                ->setType(implode('|', $options['type']))
                ->setPublic()
                ->setNullable($options['nullable']);
        }

        $namespace->add($class);
        $this->putFile($namespace);

        return $class;
    }

    private function makeClassName(string $name): string {
        $name_chunks = explode(' ', $name);

        foreach ($name_chunks as $index => $name_chunk) {
            $name_chunks[$index] = ucfirst($name_chunk);
        }

        return implode('', $name_chunks);
    }

    private function addComments(
        ClassType|InterfaceType|TraitType|EnumType $class,
        string $name,
        string $comment = null,
    ): void {
        $class->addComment(StringHelper::wrap($name));
        if ($comment) {
            $class->addComment('');
            $class->addComment(StringHelper::wrap($comment));
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
                str_replace('\\', DIRECTORY_SEPARATOR, $namespace->getName()),
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
}