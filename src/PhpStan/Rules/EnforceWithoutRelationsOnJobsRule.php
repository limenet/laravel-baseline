<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\PhpStan\Rules;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Attributes\WithoutRelations;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
final class EnforceWithoutRelationsOnJobsRule implements Rule
{
    private const SHOULD_QUEUE_IFACE = ShouldQueue::class;

    private const ELOQUENT_MODEL = Model::class;

    private const WITHOUT_RELATIONS = WithoutRelations::class;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param  ClassMethod  $node
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only constructors
        if ($node->name->toString() !== '__construct') {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        // ✅ Check if the class itself has #[WithoutRelations]
        if ($this->hasWithoutRelationsAttributeOnClass($classReflection)) {
            return []; // class-level attribute present → skip all parameters
        }

        if (!$this->classImplementsShouldQueue($classReflection)) {
            return [];
        }

        $errors = [];
        foreach ($node->params as $param) {
            $modelClass = $this->resolveModelClassFromParam($param, $scope);
            if ($modelClass === null) {
                continue; // Not a model-typed param
            }

            if ($this->hasWithoutRelationsAttribute($param)) {
                continue;
            }

            // Compose a helpful message
            $paramName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                ? '$'.$param->var->name
                : 'parameter';

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Queued job constructor parameter %s (%s) must be marked with #[WithoutRelations] to avoid serializing loaded relations.',
                $paramName,
                $modelClass,
            ))
                ->identifier('laravel.withoutRelations.missing')
                ->tip('Add: #[\\'.self::WITHOUT_RELATIONS.'] before the parameter (e.g. #[WithoutRelations] public User $user).')
                ->build();
        }

        return $errors;
    }

    private function classImplementsShouldQueue(ClassReflection $classReflection): bool
    {
        if ($classReflection->implementsInterface(self::SHOULD_QUEUE_IFACE)) {
            return true;
        }

        // Check parents / traits don’t matter here; interface is enough
        foreach ($classReflection->getInterfaces() as $iface) {
            if ($iface->getName() === self::SHOULD_QUEUE_IFACE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns fully-qualified class name if the parameter is an Eloquent Model (or subclass), otherwise null.
     */
    private function resolveModelClassFromParam(Param $param, Scope $scope): ?string
    {
        $typeNode = $param->type;

        if ($typeNode === null) {
            return null;
        }

        // Handle union types: Foo|Bar
        if ($typeNode instanceof Node\UnionType) {
            foreach ($typeNode->types as $inner) {
                if ($inner instanceof Name) {
                    $fqcn = $this->resolveNameToClass($inner, $scope);
                    if ($fqcn !== null && $this->isModelClass($fqcn)) {
                        return $fqcn; // return the first Model subclass found
                    }
                }
            }

            return null;
        }

        // Handle single named type
        if ($typeNode instanceof Name) {
            $fqcn = $this->resolveNameToClass($typeNode, $scope);
            if ($fqcn !== null && $this->isModelClass($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }

    private function isModelClass(string $fqcn): bool
    {
        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return false;
        }

        $paramClass = $this->reflectionProvider->getClass($fqcn);

        // If it's literally Model
        if ($paramClass->getName() === self::ELOQUENT_MODEL) {
            return true;
        }

        if ($this->reflectionProvider->hasClass(self::ELOQUENT_MODEL)) {
            $modelReflection = $this->reflectionProvider->getClass(self::ELOQUENT_MODEL);

            return $paramClass->isSubclassOfClass($modelReflection);
        }

        return false;
    }

    /**
     * Try to resolve a name (possibly imported or relative) to a FQCN.
     */
    private function resolveNameToClass(Name $name, Scope $scope): ?string
    {
        // If already fully qualified, done
        $asString = $name->toString();

        if ($name->isFullyQualified()) {
            return ltrim($asString, '\\');
        }

        // PHPStan can usually resolve class names in scope via reflection provider
        // Try the "as written"
        if ($this->reflectionProvider->hasClass($asString)) {
            return $asString;
        }

        return null;
    }

    private function hasWithoutRelationsAttributeOnClass(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getAttributes() as $attribute) {
            $name = ltrim($attribute->getName(), '\\');
            if ($name === self::WITHOUT_RELATIONS) {
                return true;
            }
        }

        return false;
    }

    private function hasWithoutRelationsAttribute(Param $param): bool
    {
        /** @var list<AttributeGroup> $groups */
        $groups = $param->attrGroups;

        foreach ($groups as $group) {
            /** @var list<Attribute> $attrs */
            $attrs = $group->attrs;
            foreach ($attrs as $attr) {
                $name = ltrim($attr->name->toString(), '\\');

                if ($name === self::WITHOUT_RELATIONS) {
                    return true;
                }
            }
        }

        return false;
    }
}
