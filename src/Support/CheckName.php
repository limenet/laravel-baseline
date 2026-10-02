<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * Check names without Illuminate\Support: the standalone runner executes in
 * projects where str() and class_basename() do not exist.
 */
final class CheckName
{
    /**
     * e.g. Limenet\...\UsesPestCheck -> usesPest
     *
     * @param  class-string  $class
     */
    public static function fromClass(string $class): string
    {
        $short = substr((string) strrchr('\\'.$class, '\\'), 1);
        $suffix = strrpos($short, 'Check');

        return lcfirst($suffix === false ? $short : substr($short, 0, $suffix));
    }

    /**
     * e.g. usesPest -> "uses Pest", matching Str::ucsplit()->implode(' ').
     */
    public static function display(string $name): string
    {
        return implode(' ', preg_split('/(?=\p{Lu})/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name]);
    }
}
