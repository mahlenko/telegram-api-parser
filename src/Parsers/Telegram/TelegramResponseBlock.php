<?php

namespace TelegramApiParser\Parsers\Telegram;

class TelegramResponseBlock
{
    public string $name;
    public string $description;
    public array $data = [];
    public ?array $response = null;

    public function __construct(string $name, string $description = null, array $data = [])
    {
        $this->name = trim($name);
        $this->description = $description ? trim($description) : '';
        $this->data = $data;
    }

    public function push($data)
    {
        $this->data[] = $data;
    }
}