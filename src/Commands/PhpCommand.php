<?php

namespace TelegramApiParser\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TelegramApiParser\Exceptions\GeneratorException;
use TelegramApiParser\Generator\PHP\Generator;

class PhpCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'telegram:make';
    protected static $defaultDescription = 'Generate PHP library';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws GeneratorException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputVersion = $input->getArgument('version') ?? '';
        $version = preg_replace('/[^0-9.]/', '', $inputVersion) ?: null;

        $realpath = realpath(__DIR__ .'/../../' . $_ENV['SOURCE_PATH']);
        if (!$realpath) {
            $output->writeln('Run <info>php console telegram:json</info>');
            return self::FAILURE;
        }

        if (!$version) {
            return $this->showHelp($realpath, $output);
        }

        $filename = $realpath . DIRECTORY_SEPARATOR . $version .'.json';
        if (!file_exists($filename)) {
            return $this->showHelp($realpath, $output);
        }

        $generator = new Generator;
        $generator->run($filename);

        return self::SUCCESS;
    }

    private function showHelp(string $path, OutputInterface $output): int {
        $versions = $this->allowVersions($path);

        $commands = [];
        foreach ($versions as $version) {
            $commands[] = 'run <info>php console telegram:make '. $version.'</info>';
        }

        $output->writeln([
            '<info>Select the available version:</info>',
            '=============================',
            ...$commands,
            'or upload latest: <info>php console telegram:json</info>',
            ''
        ]);

        return self::FAILURE;
    }

    private function allowVersions(string $path): array {
        $versions = [];

        foreach (scandir($path) as $filename) {
            $filename = str_replace('.json', '', $filename);
            if (in_array($filename, ['.', '..'])) continue;

            $versions[] = preg_replace('/[^0-9.]/', '', $filename);
        }

        return $versions;
    }

    protected function configure() {
        $this->addArgument(
            'version',
            InputArgument::OPTIONAL,
            'Version API (source path)',
        );
    }
}