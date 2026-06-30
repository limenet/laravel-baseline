<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\ClassConstInfo;
use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use PhpParser\Node;
use PhpParser\NodeFinder;

class LogsAsJsonCheck extends AbstractFixableCheck
{
    /**
     * Laravel's exception-context-enriching formatter, available from Laravel 13.6.
     */
    private const FORMATTER = 'Illuminate\Log\Formatters\JsonFormatter';

    public function fix(bool $dry = false): CheckResult
    {
        // The Illuminate JsonFormatter only exists from Laravel 13.6; warn (don't
        // enforce or auto-inject) on older versions, matching how other
        // version-gated checks behave.
        if (!$this->composerPackageSatisfies('laravel/framework', '>=13.6.0')) {
            return CheckResult::WARN;
        }

        $loggingConfig = $this->parsePhpConfigFile('config/logging.php');

        if ($loggingConfig === null) {
            return CheckResult::FAIL;
        }

        if ($this->hasJsonChannel($loggingConfig)) {
            return CheckResult::PASS;
        }

        $this->addComment('No log channel uses a JSON formatter: Set \'formatter\' => Illuminate\Log\Formatters\JsonFormatter::class on a monolog channel in config/logging.php for structured logs');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addJsonChannel();

        return $this->fix(dry: true);
    }

    /**
     * @param  array<string|int, mixed>  $loggingConfig
     */
    private function hasJsonChannel(array $loggingConfig): bool
    {
        $channels = $loggingConfig['channels'] ?? [];

        if (!is_array($channels)) {
            return false;
        }

        foreach ($channels as $channel) {
            if (is_array($channel) && $this->usesJsonFormatter($channel['formatter'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function usesJsonFormatter(mixed $formatter): bool
    {
        return $formatter instanceof ClassConstInfo
            && $formatter->constant === 'class'
            && str_ends_with($formatter->class, 'JsonFormatter');
    }

    /**
     * Append a dedicated `json` channel (monolog driver writing JSON to stderr)
     * to the channels array, leaving the project's existing channels untouched.
     */
    private function addJsonChannel(): void
    {
        $file = base_path('config/logging.php');

        if (!file_exists($file)) {
            return;
        }

        $writer = PhpFileWriter::open($file);
        $finder = new NodeFinder();

        $channels = $finder->findFirst(
            $writer->stmts,
            fn ($n): bool => $n instanceof Node\ArrayItem
                && $n->key instanceof Node\Scalar\String_
                && $n->key->value === 'channels'
                && $n->value instanceof Node\Expr\Array_,
        );

        if (!$channels instanceof Node\ArrayItem || !$channels->value instanceof Node\Expr\Array_) {
            return;
        }

        // Don't duplicate a channel we already added.
        foreach ($channels->value->items as $item) {
            if ($item instanceof Node\ArrayItem
                && $item->key instanceof Node\Scalar\String_
                && $item->key->value === 'json'
            ) {
                return;
            }
        }

        $channels->value->items[] = new Node\ArrayItem(
            $this->jsonChannelNode(),
            new Node\Scalar\String_('json'),
        );

        $writer->save();
    }

    private function jsonChannelNode(): Node\Expr\Array_
    {
        $string = static fn (string $key, string $value): Node\ArrayItem => new Node\ArrayItem(
            new Node\Scalar\String_($value),
            new Node\Scalar\String_($key),
        );

        return new Node\Expr\Array_([
            $string('driver', 'monolog'),
            $string('level', 'debug'),
            new Node\ArrayItem(
                new Node\Expr\ClassConstFetch(
                    new Node\Name\FullyQualified('Monolog\Handler\StreamHandler'),
                    'class',
                ),
                new Node\Scalar\String_('handler'),
            ),
            new Node\ArrayItem(
                new Node\Expr\Array_([
                    $string('stream', 'php://stderr'),
                ], ['kind' => Node\Expr\Array_::KIND_SHORT]),
                new Node\Scalar\String_('handler_with'),
            ),
            new Node\ArrayItem(
                new Node\Expr\ClassConstFetch(
                    new Node\Name\FullyQualified(self::FORMATTER),
                    'class',
                ),
                new Node\Scalar\String_('formatter'),
            ),
        ], ['kind' => Node\Expr\Array_::KIND_SHORT]);
    }
}
