<?php

namespace Limenet\LaravelBaseline\Backup;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor for extracting values from PHP config files.
 *
 * Parses array structures and extracts values at specific paths,
 * including function calls like env(), config(), and base_path().
 */
class BackupConfigVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string|int, mixed>
     */
    private array $extractedConfig = [];

    /**
     * Get the extracted configuration array.
     *
     * @return array<string|int, mixed>
     */
    public function getConfig(): array
    {
        return $this->extractedConfig;
    }

    public function enterNode(Node $node): ?int
    {
        // Look for the return statement that returns the config array
        if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\Array_) {
            $this->extractedConfig = $this->parseArray($node->expr);
        }

        return null;
    }

    /**
     * Parse an array node into a PHP array with special handling for function calls.
     *
     * @return array<string|int, mixed>
     */
    private function parseArray(Node\Expr\Array_ $array): array
    {
        $result = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $key = $this->parseKey($item->key);
            $value = $this->parseValue($item->value);

            if ($key !== null) {
                $result[$key] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Parse an array key node.
     */
    private function parseKey(?Node\Expr $key): string|int|null
    {
        if ($key === null) {
            return null;
        }

        if ($key instanceof Node\Scalar\String_) {
            return $key->value;
        }

        if ($key instanceof Node\Scalar\LNumber) {
            return $key->value;
        }

        return null;
    }

    /**
     * Parse a value node into a PHP value or FuncCallInfo.
     */
    private function parseValue(Node\Expr $value): mixed
    {
        // String literal
        if ($value instanceof Node\Scalar\String_) {
            return $value->value;
        }

        // Integer literal
        if ($value instanceof Node\Scalar\LNumber) {
            return $value->value;
        }

        // Float literal
        if ($value instanceof Node\Scalar\DNumber) {
            return $value->value;
        }

        // Boolean/null constants
        if ($value instanceof Node\Expr\ConstFetch) {
            $name = strtolower($value->name->toString());

            return match ($name) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $value->name->toString(),
            };
        }

        // Function call (env, config, base_path, etc.)
        if ($value instanceof Node\Expr\FuncCall) {
            return $this->parseFuncCall($value);
        }

        // Nested array
        if ($value instanceof Node\Expr\Array_) {
            return $this->parseArray($value);
        }

        // Class constant fetch (e.g., ZipArchive::CREATE)
        if ($value instanceof Node\Expr\ClassConstFetch) {
            return new ClassConstInfo(
                $value->class instanceof Node\Name ? $value->class->toString() : 'unknown',
                $value->name instanceof Node\Identifier ? $value->name->toString() : 'unknown',
            );
        }

        // Static property fetch
        if ($value instanceof Node\Expr\StaticPropertyFetch) {
            return new StaticPropertyInfo(
                $value->class instanceof Node\Name ? $value->class->toString() : 'unknown',
                $value->name instanceof Node\VarLikeIdentifier ? $value->name->toString() : 'unknown',
            );
        }

        // Unhandled node type - return a marker
        return new UnparsedNode($value::class);
    }

    /**
     * Parse a function call node into FuncCallInfo.
     */
    private function parseFuncCall(Node\Expr\FuncCall $funcCall): FuncCallInfo
    {
        $name = $funcCall->name instanceof Node\Name
            ? $funcCall->name->toString()
            : 'unknown';

        $args = [];
        foreach ($funcCall->args as $arg) {
            if ($arg instanceof Node\Arg) {
                $args[] = $this->parseValue($arg->value);
            }
        }

        return new FuncCallInfo($name, $args);
    }
}
