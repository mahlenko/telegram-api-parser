<?php

namespace TelegramApiParser\Console;

use DiDom\Exceptions\InvalidSelectorException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TelegramApiParser\ParserDocumentation\DocumentationParser;

class ParseCommand extends Command
{
    protected static $defaultName = 'telegram:parse';

    protected static $defaultDescription = '';

    public const VERSIONS_DIRECTORY = __DIR__ .'/../../api_versions';

    /**
     * @throws \DateMalformedStringException
     * @throws InvalidSelectorException
     */
    public function execute(InputInterface $input, OutputInterface $output): int {
        $documentation_parser = new DocumentationParser();

        $version = $documentation_parser->version();
        $date = $documentation_parser->latestDate();

        $data = [
            'version' => $version,
            'date' => $date,
            'version_string' => 'Bot API '. $version .' (Release: '. $date->format('Y-m-d') .')',
            'documentation' => $documentation_parser->handle()
        ];

        if (!file_exists(self::VERSIONS_DIRECTORY)) {
            mkdir(self::VERSIONS_DIRECTORY);
        }

        $realpath = realpath(self::VERSIONS_DIRECTORY);
        file_put_contents($realpath. DIRECTORY_SEPARATOR .$version.'.json', json_encode($data, JSON_PRETTY_PRINT));

        $output->writeln($version);

        return self::SUCCESS;
    }
}