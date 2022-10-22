<?php

namespace TelegramApiParser\Parsers;

interface ResponseParseInterface
{
    public function __toArray(): array;
}