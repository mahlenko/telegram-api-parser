<?php

namespace TelegramApiParser\Commands;

use DiDom\Exceptions\InvalidSelectorException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TelegramApiParser\Parsers\Telegram\TelegramParser;
use TelegramApiParser\Parsers\Telegram\TelegramResponse;

class JsonCommand extends Command
{
    protected static $defaultName = 'telegram:json';
    protected static $defaultDescription = 'Generate JSON telegram bot api';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws InvalidSelectorException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Creating Telegram API JSON format.</info>');

        $parser = new TelegramParser($_ENV['TELEGRAM_DOCUMENTATION_URL']);

        $response = $parser->handle();

        if (empty($response)) {
            $output->writeln('🛑Failure get api.');
            return self::INVALID;
        }

        $filename = $_ENV['FILENAME_JSON'];
        $this->createFolders($filename);

        $data = [
            'items' => $response->toArray(),
            'version' => $parser->version(),
            'modified_at' => $parser->latestDate(),
        ];

        if (file_put_contents($filename, json_encode($data))) {
            $output->writeln('<comment>File created:</comment> <info>' . realpath($filename) .'</info>');
            $output->writeln('<info>Version: '. $data['version'] .'</info>');
            $output->writeln('<info>Date API: '. $data['modified_at'] .'</info>');

            $this->showResult($response, $output);
        }

        return self::SUCCESS;
    }

    private function showResult(TelegramResponse $response, OutputInterface $output)
    {
        foreach ($response->toArray() as $item) {
            $table = new Table($output);
            $table->setHeaderTitle($item->name);
            $table->addRow([
                implode("\n", array_column($item->data, 'name')),
                $item->description
            ]);

            $table->setFooterTitle(count($item->data). ' items');
            $table->setColumnWidth(0, 40);
            $table->setColumnWidth(1, 80);
            $table->setColumnMaxWidth(1, 80);
            $table->setHeaders(['Methods', 'Description']);
            $table->render();
        }
    }

    private function createFolders(string $filename): void
    {
        $folder = str_replace(basename($filename), '', $filename);
        $folder = trim(trim($folder, '/'), DIRECTORY_SEPARATOR);

        $path = '';
        foreach (explode(DIRECTORY_SEPARATOR, $folder) as $segment) {
            $path .= $segment . DIRECTORY_SEPARATOR;
            if (!file_exists($path) || !is_dir($path)) {
                mkdir($path);
            }
        }
    }
}