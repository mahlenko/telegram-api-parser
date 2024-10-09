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

        $contracts = $this->makeContracts();

        foreach ($documentation as $group) {
            if (in_array($group->name, $excludeGroupsName))
                continue;

            foreach ($group->sections as $class) {
                if (str_contains($class->name, ' '))
                    continue;

                $type = isset($class->return) ? DataTypeEnum::METHOD : DataTypeEnum::TYPE;

                /** Create class */
                $template = $this->makeClassTemplate($class, $extends);
                $template->addComment(PHP_EOL.'@version Telegram Bot API '. $source->version);

                /** Create namespace */
                $namespace = $this->makeNamespace($type->toString());
                $namespace->add($template);

                $contract = $contracts[$type->toString()];
                $template->addImplement($contract);
                $namespace->addUse($contract);

                /** Add dependencies */
                $this->addDependencies($namespace, $template);

                $this->print($namespace);
            }
        }
    }

    /**
     * @param  mixed  $class
     * @param  string|null  $extends
     * @return ClassType
     */
    private function makeClassTemplate(mixed $class, string $extends = null): ClassType {
        $template = (new ClassType(ucfirst($class->name)))
            ->setComment(wordwrap($class->description, self::WRAP_LENGTH))
            ->setFinal();

        if ($extends)
            $template->setExtends($extends);

        if (isset($class->parameters)) {
            $method = $template
                ->addMethod('__construct')
                ->setPublic();

            foreach ($class->parameters as $parameter) {
                $method->addPromotedParameter($parameter->name)
                    ->setType($this->typeGenerator->getType($parameter->type, DataTypeEnum::TYPE))
                    ->setComment(wordwrap($parameter->description, self::WRAP_LENGTH))
                    ->addComment(sprintf('@var %s', $this->typeGenerator->toString($parameter->type, !$parameter->required)))
                    ->setNullable(!$parameter->required)
                    ->setPublic();
            }
        }

        return $template;
    }

    /**
     * @return array<InterfaceType>
     */
    private function makeContracts(): array {
        $namespace = $this->makeNamespace('Contracts');

        $contracts = [
            DataTypeEnum::TYPE->toString() => 'TelegramTypeContract',
            DataTypeEnum::METHOD->toString() => 'TelegramMethodContract'
        ];

        $namespaces = [];
        foreach ($contracts as $type => $name) {
            $namespace->add(new InterfaceType($name));
            $this->print($namespace);
            $namespace->removeClass($name);

            $namespaces[$type] = $namespace->getName() .'\\'. $name;
        }

        return $namespaces;
    }

    /**
     * @param  PhpNamespace  $namespace
     * @param  ClassType  $classType
     * @return void
     */
    private function addDependencies(PhpNamespace $namespace, ClassType $classType): void {
        /* From constructor parameters */
        if ($classType->hasMethod('__construct')) {
            $used = [];
            foreach ($classType->getMethod('__construct')->getParameters() as $parameter) {
                $types = explode('|', $parameter->getType());
                $used = array_merge(
                    $used,
                    $this->typeGenerator->excludeDefaultTypes($classType->getName(), $types, DataTypeEnum::TYPE),
                );
            }

            foreach (array_unique($used) as $use) {
                $namespace->addUse($use);
            }
        }

        if ($classType->getExtends())
            $namespace->addUse($classType->getExtends());
    }

    private function makeNamespace(string $name = null): PhpNamespace {
        $namespace = trim(self::NAMESPACE, '\\');

        if (!empty($namespace) && $name)
            $name = '\\' . $name;

        return new PhpNamespace($namespace . $name);
    }

    /**
     * @param  PhpNamespace  $namespace
     * @return void
     */
    private function print(PhpNamespace $namespace): void {
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