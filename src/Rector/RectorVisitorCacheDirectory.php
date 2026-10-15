<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

/**
 * Matches `withCache()` whose cacheDirectory names the payload's directory,
 * relative to rector.php: `__DIR__.'/.cache/rector'` or `'.cache/rector'`.
 */
class RectorVisitorCacheDirectory extends AbstractRectorVisitor
{
    /**
     * The cacheDirectory argument, named or first positional; null when absent.
     */
    public static function cacheDirectoryArg(Node\Expr\MethodCall $node): ?Node\Arg
    {
        foreach ($node->args as $index => $arg) {
            if (!$arg instanceof Node\Arg) {
                continue;
            }

            if ($arg->name instanceof Node\Identifier ? $arg->name->toString() === 'cacheDirectory' : $index === 0) {
                return $arg;
            }
        }

        return null;
    }

    public function getErrorMessage(): string
    {
        return sprintf(
            "Rector cache outside the project: Missing or incorrect call to %s() in rector.php - Expected cacheDirectory: __DIR__.'/%s' so CI can cache it",
            $this->methodName,
            $this->payload[0],
        );
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        $value = self::cacheDirectoryArg($node)?->value;

        $path = match (true) {
            $value instanceof Node\Scalar\String_ => $value->value,
            $value instanceof Node\Expr\BinaryOp\Concat
                && $value->left instanceof Node\Scalar\MagicConst\Dir
                && $value->right instanceof Node\Scalar\String_ => $value->right->value,
            default => null,
        };

        if ($path === null) {
            return false;
        }

        $path = (string) preg_replace('#^\./#', '', ltrim($path, '/'));

        return trim($path, '/') === trim($this->payload[0], '/');
    }
}
