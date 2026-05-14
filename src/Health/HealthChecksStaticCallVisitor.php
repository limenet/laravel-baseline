<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;

class HealthChecksStaticCallVisitor extends AbstractHealthChecksVisitor
{
    private bool $found = false;

    /** @var list<string> */
    private array $missingClasses = [];

    /**
     * @param  list<string>  $requiredClassNames  Short class names, e.g. 'CacheCheck'
     */
    public function __construct(private readonly array $requiredClassNames) {}

    public function wasFound(): bool
    {
        return $this->found;
    }

    /** @return list<string> */
    public function getMissingClasses(): array
    {
        return $this->missingClasses;
    }

    protected function processChecksArray(Node\Expr\Array_ $array): void
    {
        $foundClasses = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $className = $this->extractRootClassName($item->value);

            if ($className !== null) {
                $foundClasses[] = $className;
            }
        }

        $this->missingClasses = array_values(array_diff($this->requiredClassNames, $foundClasses));
        $this->found = $this->missingClasses === [];
    }
}
