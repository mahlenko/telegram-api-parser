<?php

namespace TelegramApiParser\CodeGenerator\PHP;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;
use TelegramApiParser\CodeGenerator\GeneratorInterface;

class PHPGenerator implements GeneratorInterface
{
    private string $outputDirectory;

    private TypeGenerator $typeGenerator;

    public const NAMESPACE = 'Appto\\TelegramBot';

    private const WRAP_LENGTH = 80;

    public function __construct(string $output) {
        if (!file_exists($output))
            mkdir($output, 0755, true);

        $this->outputDirectory = realpath($output);

        $this->typeGenerator = new TypeGenerator();
    }

    /**
     * @param  string  $file_source
     * @param  string|null  $extends
     * @return void
     */
    public function handle(string $file_source, string $extends = null): void {
        $source = json_decode(file_get_contents($file_source));
        $documentation = $source->documentation;

        $excludeGroupsName = [
            'Recent changes', 'Authorizing your bot',
            'Making requests', 'Using a Local Bot API Server',
        ];

        /* creating basic contracts for types and methods */
        $contracts = $this->makeContracts();

        /* creating classes for documentation types and methods */
        foreach ($documentation as $group) {
            if (in_array($group->name, $excludeGroupsName))
                continue;

            foreach ($group->sections as $class) {
                if (str_contains($class->name, ' '))
                    continue;

                $document = $this->generate($class, $extends, $contracts, $source->version);

                $this->writeToFile($document);
            }
        }
    }

    /**
     * Generate class
     *
     * @param  mixed  $class
     * @param  string|null  $extends
     * @param  array  $contracts
     * @return PhpNamespace
     */
    private function generate(
        mixed $class,
        string $extends = null,
        array $contracts = [],
        string $version = null
    ): PhpNamespace {
        $type = isset($class->return) ? DataTypeEnum::METHOD : DataTypeEnum::TYPE;

        $document = (new ClassType(ucfirst($class->name)))
            ->setComment(wordwrap($class->description, self::WRAP_LENGTH))
            ->setFinal();

        if (!empty($version)) {
            $document->addComment(PHP_EOL.'@version Telegram Bot API '.$version);
        }

        /* adds the class to the namespace */
        $namespace = $this->getNamespace($type->toString());
        $namespace->add($document);

        if ($extends) {
            $document->setExtends($extends);
            $namespace->addUse($extends);
        }

        /* adds a constructor method and describes its arguments */
        if (isset($class->parameters)) {
            $method = $document
                ->addMethod('__construct')
                ->setPublic();

            /* add parameters */
            foreach ($class->parameters as $parameter) {
                $method->addPromotedParameter($parameter->name)
                    ->setType($this->typeGenerator->toStringReturn($parameter->type))
                    ->setComment(wordwrap($parameter->description, self::WRAP_LENGTH))
                    ->addComment(sprintf('@var %s', $this->typeGenerator->toStringDocBlock($parameter->type)))
                    ->setNullable(!$parameter->required)
                    ->setPublic();
            }
        }

        /* adds a contract to the class */
        if ($contracts && key_exists($type->toString(), $contracts)) {
            $contract = $contracts[$type->toString()];
            $document->addImplement($contract);
            $namespace->addUse($contract);
        }

        /* adding custom dependencies */
        if (isset($class->parameters)) {
            $this->addDependencies($namespace, $class->parameters);
        }

        return $namespace;
    }

    /**
     * @return InterfaceType[]
     */
    private function makeContracts(): array {
        $namespace = $this->getNamespace('Contracts');

        $contracts = [
            DataTypeEnum::TYPE->toString() => 'TelegramTypeContract',
            DataTypeEnum::METHOD->toString() => 'TelegramMethodContract'
        ];

        $namespaces = [];
        foreach ($contracts as $type => $name) {
            $namespace->add(new InterfaceType($name));
            $this->writeToFile($namespace);
            $namespace->removeClass($name);

            $namespaces[$type] = $namespace->getName() .'\\'. $name;
        }

        return $namespaces;
    }

    /**
     * @param  PhpNamespace  $namespace
     * @param  string|array  $parameters
     * @return void
     */
    private function addDependencies(PhpNamespace $namespace, array $parameters): void {
        foreach ($parameters as $parameter) {
            $use = $this->typeGenerator->getDependenciesList($parameter->type);
            if ($use) {
                foreach ($use as $value) {
                    $namespace->addUse($value);
                }
            }
        }
    }

    /**
     * @param  string|null  $name
     * @return PhpNamespace
     */
    private function getNamespace(string $name = null): PhpNamespace {
        $namespace = trim(self::NAMESPACE, '\\');

        if (!empty($namespace) && $name)
            $name = '\\' . $name;

        return new PhpNamespace($namespace . $name);
    }

    /**
     * @param  PhpNamespace  $namespace
     * @return void
     */
    private function writeToFile(PhpNamespace $namespace): void {
        $printer = new Printer();
        $printer->indentation = '    ';

        $content = '<?php' . PHP_EOL . $printer->printNamespace($namespace);

        $type = str_replace('\\', '/', $namespace->getName());
        $directory = sprintf('%s/%s', $this->outputDirectory, $type);
        $filename = array_key_first($namespace->getClasses()) .'.php';
        $filepath = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($directory))
            mkdir($directory, 0755, true);

        file_put_contents($filepath, $content);
    }
}