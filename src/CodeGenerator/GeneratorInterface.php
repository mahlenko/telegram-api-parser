<?php

namespace TelegramApiParser\CodeGenerator;

interface GeneratorInterface
{
    public function handle(string $file_source): void;
}