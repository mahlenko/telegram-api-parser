<?php

namespace TelegramApiParser\CodeGenerator;

class StringHelper
{
    public static function wrap(string $string, int $size = 80): string {
        $string = str_replace(['“', '”'], ['"', '"'], $string);
        $separator = '####';
        $output = explode($separator, wordwrap($string, $size, $separator));

        return implode(PHP_EOL, $output);
    }
}