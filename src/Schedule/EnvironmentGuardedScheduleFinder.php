<?php

namespace Limenet\LaravelBaseline\Schedule;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * Finds schedule entries that the booted application does not register because they sit behind an
 * environment guard, e.g. `if (app()->isProduction()) { $schedule->command('backup:run'); }`.
 *
 * The baseline runs locally and in CI, so such an entry is invisible to `Schedule::events()` while
 * being exactly what the project runs in production. Only string commands are recognised; an entry
 * registered by class name would need the command's signature, which the source does not carry.
 */
class EnvironmentGuardedScheduleFinder
{
    private const array GUARD_METHODS = ['isproduction', 'islocal', 'environment', 'runningunittests'];

    /**
     * @param  list<string>  $files  absolute paths; missing or unparsable files are skipped
     */
    public function contains(array $files, string $command): bool
    {
        foreach ($files as $file) {
            if ($this->fileContains($file, $command)) {
                return true;
            }
        }

        return false;
    }

    private function fileContains(string $file, string $command): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $code = file_get_contents($file);

        if ($code === false) {
            return false;
        }

        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        } catch (Error) {
            return false;
        }

        if ($ast === null) {
            return false;
        }

        $traverser = new NodeTraverser(new ParentConnectingVisitor);
        $ast = $traverser->traverse($ast);

        $calls = (new NodeFinder)->find(
            $ast,
            fn (Node $node): bool => $this->isScheduleCommand($node, $command),
        );

        foreach ($calls as $call) {
            if ($this->onlyEnvironmentGuarded($call)) {
                return true;
            }
        }

        return false;
    }

    private function isScheduleCommand(Node $node, string $command): bool
    {
        if ($node instanceof Expr\StaticCall) {
            if (!$node->class instanceof Node\Name || $node->class->getLast() !== 'Schedule') {
                return false;
            }
        } elseif ($node instanceof Expr\MethodCall) {
            if (!$node->var instanceof Expr\Variable || $node->var->name === 'this') {
                return false;
            }
        } else {
            return false;
        }

        if (!$node->name instanceof Node\Identifier || $node->name->toLowerString() !== 'command') {
            return false;
        }

        $first = $node->getArgs()[0] ?? null;

        return $first !== null
            && $first->value instanceof Node\Scalar\String_
            && str_contains($first->value->value, $command);
    }

    /**
     * Every conditional between the call and the top of the file must branch on the environment.
     */
    private function onlyEnvironmentGuarded(Node $call): bool
    {
        $child = $call;
        $parent = $call->getAttribute('parent');

        while ($parent instanceof Node) {
            foreach ($this->governingConditions($parent, $child) as $condition) {
                if (!$this->isEnvironmentGuard($condition)) {
                    return false;
                }
            }

            $child = $parent;
            $parent = $parent->getAttribute('parent');
        }

        return true;
    }

    /**
     * The conditions that decide whether `$child` runs when reached through `$parent`.
     *
     * @return list<Expr>
     */
    private function governingConditions(Node $parent, Node $child): array
    {
        if ($parent instanceof Stmt\If_) {
            // The condition itself is evaluated unconditionally.
            return $child === $parent->cond ? [] : [$parent->cond];
        }

        if ($parent instanceof Stmt\ElseIf_) {
            return $child === $parent->cond ? [] : [$parent->cond];
        }

        if ($parent instanceof Stmt\Else_) {
            $if = $parent->getAttribute('parent');

            if (!$if instanceof Stmt\If_) {
                return [];
            }

            $conditions = [$if->cond];

            foreach ($if->elseifs as $elseIf) {
                $conditions[] = $elseIf->cond;
            }

            return $conditions;
        }

        if ($parent instanceof Expr\Ternary) {
            return $child === $parent->cond ? [] : [$parent->cond];
        }

        return [];
    }

    private function isEnvironmentGuard(Expr $expr): bool
    {
        if ($expr instanceof Expr\BooleanNot) {
            return $this->isEnvironmentGuard($expr->expr);
        }

        if (
            $expr instanceof Expr\BinaryOp\BooleanAnd
            || $expr instanceof Expr\BinaryOp\BooleanOr
            || $expr instanceof Expr\BinaryOp\LogicalAnd
            || $expr instanceof Expr\BinaryOp\LogicalOr
        ) {
            return $this->isEnvironmentGuard($expr->left) && $this->isEnvironmentGuard($expr->right);
        }

        if (
            $expr instanceof Expr\BinaryOp\Identical
            || $expr instanceof Expr\BinaryOp\NotIdentical
            || $expr instanceof Expr\BinaryOp\Equal
            || $expr instanceof Expr\BinaryOp\NotEqual
        ) {
            return $this->readsEnvironmentName($expr->left) || $this->readsEnvironmentName($expr->right);
        }

        if ($expr instanceof Expr\MethodCall) {
            return $this->isGuardMethod($expr->name) && $this->isApplication($expr->var);
        }

        if ($expr instanceof Expr\StaticCall) {
            return $this->isGuardMethod($expr->name)
                && $expr->class instanceof Node\Name
                && $expr->class->getLast() === 'App';
        }

        return false;
    }

    private function isGuardMethod(Node $name): bool
    {
        return $name instanceof Node\Identifier && in_array($name->toLowerString(), self::GUARD_METHODS, true);
    }

    /**
     * `app()`, `$app` or `$this->app`.
     */
    private function isApplication(Expr $expr): bool
    {
        if ($expr instanceof Expr\FuncCall) {
            return $expr->name instanceof Node\Name
                && $expr->name->toLowerString() === 'app'
                && $expr->getArgs() === [];
        }

        if ($expr instanceof Expr\Variable) {
            return $expr->name === 'app';
        }

        return $expr instanceof Expr\PropertyFetch
            && $expr->var instanceof Expr\Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'app';
    }

    /**
     * `config('app.env')` or `env('APP_ENV')`.
     */
    private function readsEnvironmentName(Expr $expr): bool
    {
        if (!$expr instanceof Expr\FuncCall || !$expr->name instanceof Node\Name) {
            return false;
        }

        $first = $expr->getArgs()[0] ?? null;

        if ($first === null || !$first->value instanceof Node\Scalar\String_) {
            return false;
        }

        return match ($expr->name->toLowerString()) {
            'config' => $first->value->value === 'app.env',
            'env' => $first->value->value === 'APP_ENV',
            default => false,
        };
    }
}
