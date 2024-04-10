<?php

namespace TelegramApiParser\CodeGenerator;

use Nette\PhpGenerator\Printer as OriginalPrinter;

class Printer extends OriginalPrinter
{
    // length of the line after which the line will break
    public int $wrapLength = 80;
    // indentation character, can be replaced with a sequence of spaces
    public string $indentation = "    ";
    // number of blank lines between properties
    public int $linesBetweenProperties = 0;
    // number of blank lines between methods
    public int $linesBetweenMethods = 2;
    // number of blank lines between 'use statements' groups for classes, functions, and constants
    public int $linesBetweenUseTypes = 0;
    // position of the opening curly brace for functions and methods
    public bool $bracesOnNextLine = true;
    // place one parameter on one line, even if it has an attribute or is supported
    public bool $singleParameterOnOneLine = false;
    // omits namespaces that do not contain any class or function
    public bool $omitEmptyNamespaces = true;
    // separator between the right parenthesis and return type of functions and methods
    public string $returnTypeColon = ': ';
}