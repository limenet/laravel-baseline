<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use PhpParser\Node;

class CacheAllowsPulseSerializableClassesCheck extends AbstractFixableCheck
{
    /**
     * Classes laravel/pulse round-trips through the cache in RemembersQueries::remember():
     * raw query rows (stdClass) wrapped in a Collection, plus the Servers card's
     * CarbonImmutable timestamp. Without them on the allow-list they come back as
     * __PHP_Incomplete_Class and every Pulse card fatals.
     *
     * @var list<string>
     */
    private const REQUIRED_CLASSES = [
        'stdClass',
        'Illuminate\Support\Collection',
        'Carbon\CarbonImmutable',
    ];

    private const CONFIG_FILE = 'config/cache.php';

    private const CONFIG_KEY = 'serializable_classes';

    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pulse')) {
            return CheckResult::WARN;
        }

        // cache.serializable_classes is only read from Laravel 13 onwards.
        if (!$this->composerPackageSatisfies('laravel/framework', '>=13.0')) {
            return CheckResult::WARN;
        }

        $file = base_path(self::CONFIG_FILE);

        // Without a published config the framework default applies, which has no
        // allow-list at all: unserialize() stays unrestricted and Pulse works.
        if (!file_exists($file)) {
            return CheckResult::PASS;
        }

        try {
            $writer = PhpFileWriter::open($file);
        } catch (\Throwable) {
            $this->addComment(self::CONFIG_FILE.' could not be parsed');

            return CheckResult::FAIL;
        }

        $config = $this->findConfigArray($writer->stmts);

        if (!$config instanceof Node\Expr\Array_) {
            $this->addComment(self::CONFIG_FILE.' does not return an array literal: \''.self::CONFIG_KEY.'\' cannot be verified');

            return CheckResult::FAIL;
        }

        $item = $this->findConfigItem($config);

        // Absent, null and true all leave unserialize() unrestricted, so Pulse works.
        if (
            !$item instanceof Node\ArrayItem
            || $this->isConstant($item->value, 'null')
            || $this->isConstant($item->value, 'true')
        ) {
            return CheckResult::PASS;
        }

        $array = $item->value instanceof Node\Expr\Array_ ? $item->value : null;

        if (!$array instanceof Node\Expr\Array_ && !$this->isConstant($item->value, 'false')) {
            $this->addComment('\''.self::CONFIG_KEY.'\' in '.self::CONFIG_FILE.' is not a literal array: set it to an array that includes '.$this->classList(self::REQUIRED_CLASSES));

            return CheckResult::FAIL;
        }

        $allowed = $array instanceof Node\Expr\Array_
            ? $this->resolveClassNames($array, $this->useAliases($writer->stmts))
            : [];

        $missing = array_values(array_filter(
            self::REQUIRED_CLASSES,
            fn (string $class): bool => !$this->allows($allowed, $class),
        ));

        if ($missing === []) {
            return CheckResult::PASS;
        }

        $this->addComment('Laravel Pulse cannot unserialize its cached data: add '.$this->classList($missing).' to the top-level \''.self::CONFIG_KEY.'\' array in '.self::CONFIG_FILE.' (a per-store entry is ignored), otherwise every Pulse card fails in production with "tried to access a property on an incomplete object"');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $items = $array instanceof Node\Expr\Array_ ? $array->items : [];

        foreach ($missing as $class) {
            $items[] = new Node\ArrayItem(new Node\Expr\ClassConstFetch(
                new Node\Name\FullyQualified($class),
                new Node\Identifier('class'),
            ));
        }

        // Replacing the whole array node (rather than appending to it) makes the
        // format-preserving printer lay the entries out one per line.
        $item->value = new Node\Expr\Array_($items, ['kind' => Node\Expr\Array_::KIND_SHORT]);

        $writer->save(multilineArrays: true);

        return $this->fix(dry: true);
    }

    /**
     * @param  Node\Stmt[]  $stmts
     */
    private function findConfigArray(array $stmts): ?Node\Expr\Array_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                return $stmt->expr;
            }
        }

        return null;
    }

    private function findConfigItem(Node\Expr\Array_ $config): ?Node\ArrayItem
    {
        $found = null;

        foreach ($config->items as $item) {
            if ($item->key instanceof Node\Scalar\String_ && $item->key->value === self::CONFIG_KEY) {
                // Keep looking: with duplicate keys PHP itself uses the last one.
                $found = $item;
            }
        }

        return $found;
    }

    private function isConstant(Node\Expr $value, string $name): bool
    {
        return $value instanceof Node\Expr\ConstFetch
            && strtolower($value->name->toString()) === $name;
    }

    /**
     * Lowercased alias => fully-qualified name, so short names in the config resolve.
     *
     * @param  Node\Stmt[]  $stmts
     * @return array<string, string>
     */
    private function useAliases(array $stmts): array
    {
        $aliases = [];

        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\Use_ || $stmt->type !== Node\Stmt\Use_::TYPE_NORMAL) {
                continue;
            }

            foreach ($stmt->uses as $use) {
                $aliases[strtolower($use->getAlias()->toString())] = $use->name->toString();
            }
        }

        return $aliases;
    }

    /**
     * @param  array<string, string>  $aliases
     * @return list<string>
     */
    private function resolveClassNames(Node\Expr\Array_ $array, array $aliases): array
    {
        $names = [];

        foreach ($array->items as $item) {
            $value = $item->value;

            if ($value instanceof Node\Scalar\String_) {
                $names[] = $value->value;

                continue;
            }

            if (
                $value instanceof Node\Expr\ClassConstFetch
                && $value->class instanceof Node\Name
                && $value->name instanceof Node\Identifier
                && $value->name->toString() === 'class'
            ) {
                $name = $value->class->toString();
                $names[] = $aliases[strtolower($name)] ?? $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function allows(array $allowed, string $class): bool
    {
        $fqcn = strtolower($class);
        $short = strtolower(class_basename($class));

        foreach ($allowed as $name) {
            $name = strtolower(ltrim($name, '\\'));

            if ($name === $fqcn) {
                return true;
            }

            // Tolerate a short name written without a matching use statement.
            if (!str_contains($name, '\\') && $name === $short) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $classes
     */
    private function classList(array $classes): string
    {
        return implode(', ', array_map(fn (string $class): string => '\\'.$class.'::class', $classes));
    }
}
