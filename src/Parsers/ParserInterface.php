<?php

namespace TelegramApiParser\Parsers;

interface ParserInterface
{
    /**
     * Сформирует API
     * @return ResponseParseInterface
     */
    public function handle(): ResponseParseInterface;

    /**
     * Версия загруженного API
     * @return mixed
     */
    public function version();
}