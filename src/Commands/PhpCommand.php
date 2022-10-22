<?php

namespace TelegramApiParser\Commands;

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
        $generator = new Generator;
        $generator->run($_ENV['FILENAME_JSON']);

        return self::SUCCESS;
    }
}