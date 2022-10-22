<?php

namespace TelegramApiParser\Parsers\Telegram;

class TelegramResponseBlock
{
    public string $name;
    public string $description;
    public array $data = [];

    public function __construct(string $name, string $description = null, array $data = [])
    {
        $this->name = trim($name);
        $this->description = trim($description);
        $this->data = $data;
    }

    public function push($data)
    {
        $this->data[] = $data;
    }
//
//    public function __toArray(): array
//    {
//        $data = [];
//        foreach ($this->data as $index => $value)
//        {
//            $data[$index] = is_object($value) ? (array) $value : $value;
//        }
//
//        return [
//            'name' => $this->name,
//            'description' => $this->description,
//            'data' => $data
//        ];
//    }
}