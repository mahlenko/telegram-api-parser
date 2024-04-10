<?php

namespace TelegramApiParser\CodeGenerator;

interface GeneratorInterface
{
    const EXCLUDE_BLOCK_NAMES = [
        'Recent changes', 'Authorizing your bot', 'Making requests', 'Using a Local Bot API Server'
    ];

    public function handle(string $file_source): void;
}