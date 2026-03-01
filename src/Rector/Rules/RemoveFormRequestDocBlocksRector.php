<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveFormRequestDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel form request PHPDoc comments from authorize() and rules() methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {}

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class StorePostRequest extends FormRequest
{
    public function authorize(): bool {}

    public function rules(): array {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Determine if the user is authorized to make this request.',
            'Get the validation rules that apply to the request.',
        ];
    }
}
