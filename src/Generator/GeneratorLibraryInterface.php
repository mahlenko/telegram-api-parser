<?php

namespace TelegramApiParser\Generator;

interface GeneratorLibraryInterface
{
    public function run(string $filename): void;
}