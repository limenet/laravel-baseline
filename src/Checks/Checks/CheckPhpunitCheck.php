<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CheckPhpunitCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $xml = $this->getPhpunitXml();

        if ($xml === null) {
            $this->addComment('PHPUnit configuration missing: Create phpunit.xml in project root');

            return CheckResult::FAIL;
        }

        if ($xml === false) {
            $this->addComment('PHPUnit configuration invalid: Check phpunit.xml for XML syntax errors');

            return CheckResult::FAIL;
        }

        if (
            ($xml->coverage->report->cobertura ?? null) === null
            || (string) $xml->coverage->report->cobertura->attributes()['outputFile'] !== 'cobertura.xml'
        ) {
            $this->addComment('Cobertura coverage report missing or misconfigured in phpunit.xml: Add <cobertura outputFile="cobertura.xml"/> under <coverage><report>');

            return CheckResult::FAIL;
        }
        if (
            ($xml->logging->junit ?? null) === null
            || (string) $xml->logging->junit->attributes()['outputFile'] !== 'report.xml'
        ) {
            $this->addComment('JUnit report missing or misconfigured in phpunit.xml: Add <junit outputFile="report.xml"/> under <logging>');

            return CheckResult::FAIL;
        }

        $appKeyFound = false;

        foreach ($xml->php->env as $env) {
            $attrs = $env->attributes();
            if ((string) $attrs['name'] === 'APP_KEY' && str_starts_with($attrs['value'], 'base64:')) {
                $appKeyFound = true;
                break;
            }
        }

        if (!$appKeyFound) {
            $this->addComment('APP_KEY missing in phpunit.xml: Add <env name="APP_KEY" value="base64:..."/> to <php> section (generate with "php artisan key:generate")');

            return CheckResult::FAIL;
        }

        // Check for source configuration
        if (
            ($xml->source->include->directory ?? null) === null
            || (string) $xml->source->include->directory !== './app'
            || (string) $xml->source->include->directory->attributes()['suffix'] !== '.php'
        ) {
            $this->addComment('Coverage source configuration missing or incorrect in phpunit.xml: Add <source><include><directory suffix=".php">./app</directory></include></source>');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;

    }
}
