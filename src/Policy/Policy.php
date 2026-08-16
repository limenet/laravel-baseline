<?php

namespace Limenet\LaravelBaseline\Policy;

/**
 * The policy values shared verbatim by this package and its npm counterpart,
 * `@limenet-ch/baseline`. Both runners read `policy/policy.json`, so a version
 * floor or a required key is bumped in exactly one place.
 *
 * Accessors narrow to concrete types and throw otherwise, so `mixed` never
 * escapes this class into the checks.
 */
final class Policy
{
    /**
     * @param  array<string,mixed>  $data
     */
    private function __construct(
        private readonly array $data,
        private readonly string $baseDirectory,
    ) {}

    /**
     * The shipped policy directory, resolved relative to this file so it works
     * both in this repo and once installed under vendor/.
     */
    public static function defaultDirectory(): string
    {
        return dirname(__DIR__, 2).'/policy';
    }

    public static function fromDirectory(?string $directory = null): self
    {
        $directory ??= self::defaultDirectory();
        $file = $directory.'/policy.json';

        if (!file_exists($file)) {
            throw PolicyException::unreadable($file);
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw PolicyException::unreadable($file);
        }

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw PolicyException::unreadable($file);
        }

        /** @var array<string,mixed> $data */
        return new self($data, $directory);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data, string $baseDirectory = ''): self
    {
        return new self($data, $baseDirectory);
    }

    public function int(string $key): int
    {
        $value = $this->lookup($key);

        return is_int($value) ? $value : throw PolicyException::wrongType($key, 'int');
    }

    public function string(string $key): string
    {
        $value = $this->lookup($key);

        return is_string($value) ? $value : throw PolicyException::wrongType($key, 'string');
    }

    public function bool(string $key): bool
    {
        $value = $this->lookup($key);

        return is_bool($value) ? $value : throw PolicyException::wrongType($key, 'bool');
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->lookup($key);

        if (!is_array($value)) {
            throw PolicyException::wrongType($key, 'list of strings');
        }

        $strings = [];

        foreach ($value as $item) {
            $strings[] = is_string($item) ? $item : throw PolicyException::wrongType($key, 'list of strings');
        }

        return $strings;
    }

    /**
     * @return array<string,string>
     */
    public function stringMap(string $key): array
    {
        $value = $this->lookup($key);

        if (!is_array($value)) {
            throw PolicyException::wrongType($key, 'map of strings');
        }

        $map = [];

        foreach ($value as $mapKey => $item) {
            if (!is_string($mapKey) || !is_string($item)) {
                throw PolicyException::wrongType($key, 'map of strings');
            }

            $map[$mapKey] = $item;
        }

        return $map;
    }

    /**
     * The verbatim body of a canonical file under policy/templates/. Multi-line
     * file bodies live there rather than JSON-escaped into policy.json, so their
     * diffs stay readable and their newlines survive both loaders unchanged.
     */
    public function template(string $name): string
    {
        $file = $this->baseDirectory.'/templates/'.$name;

        if (!file_exists($file)) {
            throw PolicyException::unreadable($file);
        }

        $contents = file_get_contents($file);

        return $contents === false ? throw PolicyException::unreadable($file) : $contents;
    }

    private function lookup(string $key): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw PolicyException::missing($key);
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
