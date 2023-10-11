<?php

namespace iggyvolz\builder;

use iggyvolz\classgen\ClassGenerator;
use LogicException;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RegexIterator;
use Stringable;

class BuilderGenerator extends ClassGenerator
{

    private const /* string */
        SUFFIX = "_builder";

    private static function typeToString(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof ReflectionUnionType) {
            return implode("|", $type->getTypes());
        }
        if ($type instanceof \ReflectionIntersectionType) {
            return implode("&", $type->getTypes());
        }
        throw new LogicException("Unrecognized type " . $type::class);
    }

    private static function nullable(?ReflectionType $type): string
    {
        if (is_null($type) || $type->allowsNull()) {
            return "mixed";
        }
        return "null|" . self::typeToString($type);
    }

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
        $classObj = $file->addNamespace($namespace)->addClass($className);
        $constructor = $classObj->addMethod("__construct");
        $buildMethod = $classObj->addMethod("build")->setReturnType($parentClass);
        $parentRefl = new \ReflectionClass($parentClass);
        foreach ($parentRefl->getConstructor()?->getParameters() ?? [] as $constructorParam) {
            $fluentMethod = $classObj->addMethod($constructorParam->name)->setReturnType("self");
            $fluentMethod->addParameter("value")->setType(self::typeToString($constructorParam->getType()));
            $fluentMethod->addBody("\$this->$constructorParam->name = \$value;")->addBody('return $this;');
            if ($constructorParam->isDefaultValueAvailable()) {
                $constructor->addPromotedParameter($constructorParam->name)->setType(self::typeToString($constructorParam->getType()))->setDefaultValue($constructorParam->getDefaultValue());
            } else {
                $constructor->addPromotedParameter($constructorParam->name)->setType(self::nullable($constructorParam->getType()))->setDefaultValue(null);
                if (!$constructorParam->allowsNull()) {
                    $buildMethod->addBody("if(\$this->$constructorParam->name === null) throw new \\RuntimeException(" . var_export("Did not include required property $constructorParam->name", true) . ");");
                }
            }
        }
        $buildMethod->addBody("return new \\$parentClass(");
        foreach ($parentRefl->getConstructor()?->getParameters() ?? [] as $constructorParam) {
            $buildMethod->addBody("    \$this->$constructorParam->name,");
        }
        $buildMethod->addBody(");");
        return (new PsrPrinter())->printFile($file);
    }
}