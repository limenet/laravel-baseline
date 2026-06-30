<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CheckPhpunitCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $xmlFile = base_path('phpunit.xml');

        if (!file_exists($xmlFile)) {
            $this->addComment('PHPUnit configuration missing: Create phpunit.xml in project root');

            return CheckResult::FAIL;
        }

        $xmlContent = file_get_contents($xmlFile);

        if ($xmlContent === false || trim($xmlContent) === '') {
            $this->addComment('PHPUnit configuration invalid: Check phpunit.xml for XML syntax errors');

            return CheckResult::FAIL;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        if (!@$dom->loadXML($xmlContent)) {
            $this->addComment('PHPUnit configuration invalid: Check phpunit.xml for XML syntax errors');

            return CheckResult::FAIL;
        }

        $root = $dom->documentElement;

        if ($root === null) {
            return CheckResult::FAIL;
        }

        $xpath = new \DOMXPath($dom);
        $dirty = false;

        // Cobertura
        $cobertura = $xpath->query('//coverage/report/cobertura[@outputFile="cobertura.xml"]');

        if ($cobertura === false || $cobertura->length === 0) {
            $this->addComment('Cobertura coverage report missing or misconfigured in phpunit.xml: Add <cobertura outputFile="cobertura.xml"/> under <coverage><report>');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->ensureXmlPath($dom, $xpath, $root, 'coverage/report/cobertura', ['outputFile' => 'cobertura.xml']);
            $dirty = true;
        }

        // JUnit
        $junit = $xpath->query('//logging/junit[@outputFile="report.xml"]');

        if ($junit === false || $junit->length === 0) {
            $this->addComment('JUnit report missing or misconfigured in phpunit.xml: Add <junit outputFile="report.xml"/> under <logging>');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->ensureXmlPath($dom, $xpath, $root, 'logging/junit', ['outputFile' => 'report.xml']);
            $dirty = true;
        }

        // APP_KEY
        $appKey = $xpath->query('//php/env[@name="APP_KEY"]');
        $appKeyValid = $appKey !== false && $appKey->length > 0
            && $appKey->item(0) instanceof \DOMElement
            && str_starts_with($appKey->item(0)->getAttribute('value'), 'base64:');

        if (!$appKeyValid) {
            $this->addComment('APP_KEY missing in phpunit.xml: Add <env name="APP_KEY" value="base64:..."/> to <php> section (generate with "ddev artisan key:generate")');

            if ($dry) {
                return CheckResult::FAIL;
            }

            if ($appKey !== false && $appKey->length > 0 && $appKey->item(0) instanceof \DOMElement) {
                $appKey->item(0)->setAttribute('value', 'base64:'.base64_encode(random_bytes(32)));
            } else {
                $phpEl = $dom->getElementsByTagName('php')->item(0);

                if ($phpEl === null) {
                    $phpEl = $dom->createElement('php');
                    $root->appendChild($phpEl);
                }

                $env = $dom->createElement('env');
                $env->setAttribute('name', 'APP_KEY');
                $env->setAttribute('value', 'base64:'.base64_encode(random_bytes(32)));
                $phpEl->appendChild($env);
            }

            $dirty = true;
        }

        // Source config
        $sourceDir = $xpath->query('//source/include/directory[@suffix=".php"]');
        $sourceDirValid = $sourceDir !== false && $sourceDir->length > 0
            && $sourceDir->item(0) instanceof \DOMElement
            && $sourceDir->item(0)->textContent === './app';

        if (!$sourceDirValid) {
            $this->addComment('Coverage source configuration missing or incorrect in phpunit.xml: Add <source><include><directory suffix=".php">./app</directory></include></source>');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->ensureSourceConfig($dom, $xpath, $root);
            $dirty = true;
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        if ($dirty) {
            $dom->save($xmlFile);
        }

        return $this->fix(dry: true);
    }

    /**
     * @param  array<string, string>  $attrs
     */
    private function ensureXmlPath(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $root, string $path, array $attrs): void
    {
        $parts = explode('/', $path);
        $current = $root;

        foreach ($parts as $i => $part) {
            $existing = $xpath->query($part, $current);

            if ($existing !== false && $existing->length > 0) {
                $node = $existing->item(0);

                if ($node instanceof \DOMElement) {
                    if ($i === count($parts) - 1) {
                        foreach ($attrs as $k => $v) {
                            $node->setAttribute($k, $v);
                        }
                    }

                    $current = $node;
                }
            } else {
                $newNode = $dom->createElement($part);

                if ($i === count($parts) - 1) {
                    foreach ($attrs as $k => $v) {
                        $newNode->setAttribute($k, $v);
                    }
                }

                $current->appendChild($newNode);
                $current = $newNode;
            }
        }
    }

    private function ensureSourceConfig(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $root): void
    {
        $existing = $xpath->query('//source/include/directory[@suffix=".php"]');

        if ($existing !== false && $existing->length > 0) {
            $node = $existing->item(0);

            if ($node instanceof \DOMElement && $node->textContent !== './app') {
                $node->nodeValue = './app';
            }

            return;
        }

        $sourceEl = $dom->getElementsByTagName('source')->item(0);

        if ($sourceEl === null) {
            $sourceEl = $dom->createElement('source');
            $root->appendChild($sourceEl);
        }

        /** @var \DOMElement $sourceEl */
        $includeEl = $sourceEl->getElementsByTagName('include')->item(0);

        if ($includeEl === null) {
            $includeEl = $dom->createElement('include');
            $sourceEl->appendChild($includeEl);
        }

        $dirEl = $dom->createElement('directory', './app');
        $dirEl->setAttribute('suffix', '.php');
        $includeEl->appendChild($dirEl);
    }
}
