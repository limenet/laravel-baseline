<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan;

use Limenet\LaravelBaseline\PhpStan\Rules\EnforceWithoutRelationsOnJobsRule;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<EnforceWithoutRelationsOnJobsRule>
 */
final class EnforceWithoutRelationsOnJobsRuleTest extends RuleTestCase
{
    public function test_reports_missing_without_relations_on_model_params_in_jobs(): void
    {
        $this->analyse(
            [__DIR__.'/Fixtures/CreateReport.php'],
            [
                [
                    'Queued job constructor parameter $user (Limenet\LaravelBaseline\Tests\PhpStan\Fixtures\User) must be marked with #[WithoutRelations] to avoid serializing loaded relations.'."\n".'    💡 Add: #[\Illuminate\Queue\Attributes\WithoutRelations] before the parameter (e.g. #[WithoutRelations] public User $user).',
                    11,
                ],
            ],
        );

        $this->analyse(
            [__DIR__.'/Fixtures/JobWithBaseModel.php'],
            [
                [
                    'Queued job constructor parameter $model (Illuminate\Database\Eloquent\Model) must be marked with #[WithoutRelations] to avoid serializing loaded relations.'."\n".'    💡 Add: #[\Illuminate\Queue\Attributes\WithoutRelations] before the parameter (e.g. #[WithoutRelations] public User $user).',
                    12,
                ],
            ],
        );

        $this->analyse(
            [__DIR__.'/Fixtures/JobWithClassLevelAttr.php'],
            [],
        );

        $this->analyse(
            [__DIR__.'/Fixtures/PlainClass.php'],
            [],
        );

        $this->analyse(
            [__DIR__.'/Fixtures/ProcessSomething.php'],
            [
                [
                    'Queued job constructor parameter $foo (Limenet\LaravelBaseline\Tests\PhpStan\Fixtures\User) must be marked with #[WithoutRelations] to avoid serializing loaded relations.'."\n".'    💡 Add: #[\Illuminate\Queue\Attributes\WithoutRelations] before the parameter (e.g. #[WithoutRelations] public User $user).',
                    11,
                ],
            ],
        );

        $this->analyse(
            [__DIR__.'/Fixtures/SendEmail.php'],
            [],
        );

        $this->analyse(
            [__DIR__.'/Fixtures/User.php'],
            [],
        );

        // Job with no constructor - should pass
        $this->analyse(
            [__DIR__.'/Fixtures/JobWithNoConstructor.php'],
            [],
        );

        // Job with untyped param - should pass (no model type)
        $this->analyse(
            [__DIR__.'/Fixtures/JobWithUntypedParam.php'],
            [],
        );

        // Job with non-model typed params - should pass
        $this->analyse(
            [__DIR__.'/Fixtures/JobWithNonModelParam.php'],
            [],
        );
    }

    protected function getRule(): Rule
    {
        $provider = self::getContainer()->getByType(ReflectionProvider::class);

        return new EnforceWithoutRelationsOnJobsRule($provider);
    }
}
