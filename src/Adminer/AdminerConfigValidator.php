<?php

namespace Limenet\LaravelBaseline\Adminer;

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class AdminerConfigValidator
{
    private const REQUIRED_MIDDLEWARE = 'adminer';

    private const REQUIRED_TFA_MIDDLEWARE = 'Wnx\TfaConfirmation\Http\Middleware\RequireTwoFactorAuthenticationConfirmation';

    /**
     * @var list<string>
     */
    private array $errors = [];

    /**
     * Validate the adminer configuration and kernel middleware.
     *
     * @return list<string> List of validation errors
     */
    public function validate(string $adminerConfigPath, string $kernelPath): array
    {
        $this->errors = [];

        $this->validateAdminerConfig($adminerConfigPath);
        $this->validateKernelMiddleware($kernelPath);

        return $this->errors;
    }

    /**
     * Validate the adminer config file.
     */
    private function validateAdminerConfig(string $configPath): void
    {
        if (!file_exists($configPath)) {
            $this->errors[] = 'Adminer configuration missing: Create config/adminer.php by running "php artisan vendor:publish --provider=\"Onecentlin\\Adminer\\ServiceProvider\""';

            return;
        }

        $code = file_get_contents($configPath);

        if ($code === false) {
            $this->errors[] = 'Adminer configuration unreadable: Unable to read config/adminer.php';

            return;
        }

        $config = $this->parseConfig($code);

        if ($config === []) {
            $this->errors[] = 'Adminer configuration invalid: Unable to parse config/adminer.php';

            return;
        }

        $middleware = $config['middleware'] ?? null;

        if ($middleware !== self::REQUIRED_MIDDLEWARE) {
            $this->errors[] = sprintf(
                'Adminer middleware misconfigured in config/adminer.php: Set "middleware" to "%s" (found: %s)',
                self::REQUIRED_MIDDLEWARE,
                is_string($middleware) ? '"'.$middleware.'"' : 'null',
            );
        }
    }

    /**
     * Validate the HTTP kernel has the adminer middleware group with TFA middleware.
     */
    private function validateKernelMiddleware(string $kernelPath): void
    {
        if (!file_exists($kernelPath)) {
            $this->errors[] = 'HTTP Kernel missing: app/Http/Kernel.php not found';

            return;
        }

        $code = file_get_contents($kernelPath);

        if ($code === false) {
            $this->errors[] = 'HTTP Kernel unreadable: Unable to read app/Http/Kernel.php';

            return;
        }

        $middlewareGroups = $this->parseKernelMiddlewareGroups($code);

        if ($middlewareGroups === null) {
            $this->errors[] = 'HTTP Kernel invalid: Unable to parse $middlewareGroups from app/Http/Kernel.php';

            return;
        }

        if (!isset($middlewareGroups[self::REQUIRED_MIDDLEWARE])) {
            $this->errors[] = sprintf(
                'Missing middleware group in app/Http/Kernel.php: Add "%s" group to $middlewareGroups array',
                self::REQUIRED_MIDDLEWARE,
            );

            return;
        }

        $adminerGroup = $middlewareGroups[self::REQUIRED_MIDDLEWARE];

        if (!is_array($adminerGroup)) {
            $this->errors[] = sprintf(
                'Invalid middleware group in app/Http/Kernel.php: "%s" group must be an array',
                self::REQUIRED_MIDDLEWARE,
            );

            return;
        }

        $hasTfaMiddleware = false;

        foreach ($adminerGroup as $middleware) {
            if (is_string($middleware) && str_contains($middleware, 'RequireTwoFactorAuthenticationConfirmation')) {
                $hasTfaMiddleware = true;
                break;
            }
        }

        if (!$hasTfaMiddleware) {
            $this->errors[] = sprintf(
                'Missing TFA middleware in app/Http/Kernel.php: Add %s::class to the "%s" middleware group',
                self::REQUIRED_TFA_MIDDLEWARE,
                self::REQUIRED_MIDDLEWARE,
            );
        }
    }

    /**
     * Parse the config file using PHP Parser.
     *
     * @return array<string|int, mixed>
     */
    private function parseConfig(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $visitor = new BackupConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getConfig();
    }

    /**
     * Parse the kernel file to extract $middlewareGroups property.
     *
     * @return array<int|string, mixed>|null
     */
    private function parseKernelMiddlewareGroups(string $code): ?array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return null;
        }

        if ($ast === null) {
            return null;
        }

        $visitor = new KernelMiddlewareVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getMiddlewareGroups();
    }
}
