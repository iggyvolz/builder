<?php

namespace iggyvolz\builder;

use iggyvolz\classgen\ClassGenerator;
use LogicException;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Stringable;

class TraitGenerator extends ClassGenerator
{

    private const /* string */
        SUFFIX = "_builderTrait";

    protected function isValid(string $class): bool
    {
        return str_ends_with($class, self::SUFFIX);
    }

    protected function generate(string $class): string|Stringable
    {
        $parentClass = substr($class, 0, -strlen(self::SUFFIX));
        if (!class_exists($parentClass)) {
            throw new LogicException("Invalid parent class $parentClass for $class");
        }
        $file = (new PhpFile())->setStrictTypes();
        $classExpl = explode("\\", $class);
        $className = array_pop($classExpl);
        $namespace = implode("\\", $classExpl);
        $trait = $file->addNamespace($namespace)->addTrait($className);
        $builderClass = substr($class, 0, -strlen("Trait"));
        $trait->addMethod("builder")->setStatic()->setReturnType($builderClass)
            ->setBody("return new \\$builderClass;");
        return (new PsrPrinter())->printFile($file);
    }


    public static function generateHelpers(string $dir): void
    {
        // Add autoloader to generate a stub, so that PHP can compile the main class
        spl_autoload_register(function (string $class) {
            if (str_ends_with($class, self::SUFFIX)) {
                $expl = explode("\\", $class);
                $classname = array_pop($expl);
                $ns = implode("\\", $expl);
                eval(<<<EOT
            namespace $ns
            {
                trait $classname
                {
                }
            }
        EOT);
            }
        }, prepend: true);

        $it = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)), '/^.+\.php$/i', RegexIterator::GET_MATCH);
        foreach ($it as $f => $_) {
            require_once $f;
        }

        self::register();
        foreach (get_declared_traits() as $class) {
            if (str_ends_with($class, self::SUFFIX)) {
                // Generate the actual class
                ClassGenerator::autoload($class);
            }
        }

    }
}