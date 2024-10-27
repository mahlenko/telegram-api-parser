<?php

namespace TelegramApiParser\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TelegramApiParser\CodeGenerator\PHP\PHPGenerator;

class GenerateClassesCommand extends Command
{
    protected static $defaultName = 'telegram:generate';

    protected static $defaultDescription = 'Generates objects from the documentation.';

    private const VERSIONS_DIRECTORY = __DIR__ .'/../../versions';

    private const GENERATORS = [
        'php' => PHPGenerator::class
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $generator_key = $input->getArgument('generator');
        if (!in_array($generator_key, array_keys(self::GENERATORS), true)) {
            $output->writeln(sprintf('<error>Generator "%s" does not exist.</error>', $generator_key));
            return Command::FAILURE;
        }

        $version = $input->getOption('v');
        if (!in_array($version, $this->versions(), true)) {
            $output->writeln('<error>Invalid version API.</error>');
            return Command::FAILURE;
        }

        $documentation_file = realpath(self::VERSIONS_DIRECTORY.'/'.$version.'.json');
        $build_output = __DIR__ .'/../../build';

        $generator = new (self::GENERATORS[$generator_key])($build_output);
        $generator->handle($documentation_file, $input->getOption('extends'));

        return Command::SUCCESS;
    }

    private function versions(): array {
        $versions = [];

        foreach (glob(self::VERSIONS_DIRECTORY . '/*.json') as $file_version) {
            $versions[] = str_replace('.json', '', basename($file_version));
        }

        return $versions;
    }

    protected function configure(): void {
        $generators = array_keys(self::GENERATORS);

        $versions = $this->versions();

        if (!count($versions)) {
            $command_name = ParseCommand::getDefaultName();
            throw new RuntimeException('First, generate the Documentation JSON. Use the '. $command_name .' command');
        }

        $this
            ->addArgument('generator', InputArgument::OPTIONAL, 'Which generator should I use? Available: '. implode(', ', $generators), $generators[0])
            ->addOption('v',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Which version of the API should I generate? Available: '. implode(', ', $versions),
                default: $versions[array_key_last($versions)]
            )->addOption('extends', mode: InputOption::VALUE_OPTIONAL);
    }
}