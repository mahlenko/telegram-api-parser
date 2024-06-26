<?php

namespace TelegramApiParser\ParserDocumentation\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TelegramApiParser\ParserDocumentation\TelegramDocumentationParser;

class ParseCommand extends Command
{
    protected static $defaultName = 'telegram:parse';

    protected static $defaultDescription = '';

    public function execute(InputInterface $input, OutputInterface $output): int {
        $documentation_parser = new TelegramDocumentationParser();

        $version = $documentation_parser->version();
        $date = $documentation_parser->latestDate();

        $data = [
            'version' => $version,
            'date' => $date,
            'version_string' => 'Bot API '. $version .' (Release: '. $date->format('Y-m-d') .')',
            'documentation' => $documentation_parser->handle()
        ];

        file_put_contents(__DIR__ .'/../../../versions/'.$version.'.json', json_encode($data, JSON_PRETTY_PRINT));
        $output->writeln('<info>Parsing version '.$version.'</info>');

        return self::SUCCESS;
    }
}