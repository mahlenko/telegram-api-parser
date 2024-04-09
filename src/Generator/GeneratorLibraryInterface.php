<?php

namespace TelegramApiParser\Generator;

interface GeneratorLibraryInterface
{
    public function run(string $filename, string $package_version): void;
}