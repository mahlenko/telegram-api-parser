<?php

namespace TelegramApiParser;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

class Helpers
{
    public function __construct(private string $version) {
    }

    /**
     * @param string $name
     * @return string
     */
    public static function className(string $name): string
    {
        $name = preg_replace('/(Getting|Available)/', '', $name);
        $name = trim($name);

        $nameFormated = '';
        foreach (explode(' ', $name) as $segment) {
            $nameFormated .= ucfirst(trim($segment));
        }

        return $nameFormated;
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function createFolderRecursive(string $filename): string
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $filename);

        $folder = DIRECTORY_SEPARATOR . $_ENV['BUILD_PATH'] . DIRECTORY_SEPARATOR
            . str_replace(basename($path), '', $path);

        $folder = trim($folder, DIRECTORY_SEPARATOR);

        $folder_path = '';
        foreach (explode(DIRECTORY_SEPARATOR, $folder) as $segment) {
            $folder_path .= $segment . DIRECTORY_SEPARATOR;
            if (!file_exists($folder_path) || !is_dir($folder_path)) {
                mkdir($folder_path);
            }
        }

        return $folder .DIRECTORY_SEPARATOR. basename($path);
    }

    /**
     * @param string $string
     * @param int $length
     * @param string $separator
     * @return string
     */
    public static function wordwrap(string $string, int $length = 70, string $separator = PHP_EOL): string
    {
        return wordwrap($string, $length, $separator);
    }

    /**
     * @param string $version
     * @param PhpNamespace $namespace
     * @param string $name
     * @return string
     */
    public function save(PhpNamespace $namespace, string $name): string
    {
        $printer = new Printer;

        $file = new PhpFile;
        $file->setStrictTypes()
            ->addNamespace($namespace);

        $pathFilename = $this->version .'\\'. $namespace->resolveName($name).'.php';
        $filename = Helpers::createFolderRecursive($pathFilename);

        file_put_contents($filename, $printer->printFile($file));

        return $filename;
    }

    /**
     * @param string $path
     * @return PhpNamespace
     */
    public static function namespace(string $path = ''): PhpNamespace
    {
        return new PhpNamespace(self::pathFromBaseNamespace($path));
    }

    public static function pathFromBaseNamespace(string $path = ''): string
    {
        if (empty($path)) {
            return rtrim($_ENV['BASE_NAMESPACE'], '\\');
        }

        $path = str_replace('/', '\\', $path);
        return $_ENV['BASE_NAMESPACE'] . $path;
    }
}