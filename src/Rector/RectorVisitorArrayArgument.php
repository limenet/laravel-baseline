<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorArrayArgument extends AbstractRectorVisitor
{
    /**
     * Entries that the call was found to be missing, once one has been examined.
     *
     * @var list<string>
     */
    private array $missing = [];

    public function getErrorMessage(): string
    {
        if ($this->missing !== []) {
            return sprintf(
                'Rector configuration incomplete: %s() in rector.php is missing %s - add %s to the array',
                $this->methodName,
                implode(', ', $this->missing),
                implode(', ', array_map(static fn (string $class): string => $class.'::class', $this->missing)),
            );
        }

        return sprintf(
            'Rector configuration incomplete: Missing or incorrect call to %s() in rector.php - Expected array containing: %s',
            $this->methodName,
            implode(', ', $this->payload),
        );
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        $args = [];

        $firstArg = $node->args[0] ?? null;
        if (!$firstArg instanceof Node\Arg) {
            return false;
        }

        $arg0 = $firstArg->value;

        if ($arg0 instanceof Node\Expr\Array_) {
            foreach ($arg0->items as $arg) {
                // Compared on the last segment, so an entry written out in full
                // (`\RectorLaravel\...\CarbonToDateFacadeRector::class`) counts
                // as present rather than being reported missing and duplicated.
                if ($arg->value instanceof Node\Expr\ClassConstFetch
                    && $arg->value->class instanceof Node\Name) {
                    $args[] = $arg->value->class->getLast();
                }
                if ($arg->key instanceof Node\Expr\ClassConstFetch
                    && $arg->key->class instanceof Node\Name) {
                    $args[] = $arg->key->class->getLast();
                }
            }
        }

        $this->missing = array_values(array_filter(
            $this->payload,
            static fn (string $name): bool => !in_array($name, $args, true),
        ));

        return $this->missing === [];
    }
}
