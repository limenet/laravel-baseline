<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorConfiguredRules extends AbstractRectorVisitor
{
    /** @var string[] */
    private array $foundClasses = [];

    public function getErrorMessage(): string
    {
        $missing = array_values(array_filter(
            $this->payload,
            fn (string $required): bool => !in_array($required, $this->foundClasses, true),
        ));

        return sprintf(
            'Rector configuration incomplete: Missing withConfiguredRule() calls in rector.php for: %s',
            implode(', ', $missing),
        );
    }

    public function wasFound(): bool
    {
        foreach ($this->payload as $required) {
            if (!in_array($required, $this->foundClasses, true)) {
                return false;
            }
        }

        return $this->payload !== [];
    }

    public function enterNode(Node $node): null
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== $this->methodName) {
            return null;
        }

        $firstArg = $node->args[0] ?? null;
        if ($firstArg instanceof Node\Arg
            && $firstArg->value instanceof Node\Expr\ClassConstFetch
            && $firstArg->value->class instanceof Node\Name) {
            $this->foundClasses[] = $firstArg->value->class->toString();
        }

        return null;
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        return true;
    }
}
