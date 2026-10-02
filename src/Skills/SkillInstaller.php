<?php

namespace Limenet\LaravelBaseline\Skills;

use Limenet\LaravelBaseline\Project\Project;

/**
 * Copies the skills shipped for standalone projects into .claude/skills/.
 *
 * A Laravel app does not need this: Laravel Boost discovers resources/boost/
 * in installed composer packages. A project without Laravel has no Boost, so
 * the standalone runner installs them itself — the same way the npm runner
 * does (js/src/commands/install-skills.ts).
 */
final class SkillInstaller
{
    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/standalone/skills';
    }

    /**
     * Every packaged skill as name => [project-relative target, contents].
     *
     * @return array<string, array{target: string, contents: string}>
     */
    public static function packaged(): array
    {
        $skills = [];
        $directories = glob(self::directory().'/*/SKILL.md') ?: [];
        sort($directories);

        foreach ($directories as $file) {
            $name = basename(dirname($file));
            $skills[$name] = [
                'target' => ".claude/skills/{$name}/SKILL.md",
                'contents' => (string) file_get_contents($file),
            ];
        }

        return $skills;
    }

    /**
     * Brings .claude/skills/ in line with the packaged skills and returns the
     * targets it wrote. The skills belong to the package, like the canonical
     * .editorconfig: a local edit is drift and gets overwritten. Skills the
     * package does not ship are left alone.
     *
     * @return list<string>
     */
    public static function sync(Project $project): array
    {
        $written = [];

        foreach (self::packaged() as $skill) {
            $path = $project->path($skill['target']);

            if (is_file($path) && file_get_contents($path) === $skill['contents']) {
                continue;
            }

            self::write($path, $skill['contents']);
            $written[] = $skill['target'];
        }

        return $written;
    }

    /**
     * Installs the packaged skills, leaving existing ones alone unless forced.
     *
     * @return array{installed: list<string>, skipped: list<string>}
     */
    public static function install(Project $project, bool $force = false): array
    {
        $result = ['installed' => [], 'skipped' => []];

        foreach (self::packaged() as $name => $skill) {
            $path = $project->path($skill['target']);

            if (is_file($path) && !$force) {
                $result['skipped'][] = $name;

                continue;
            }

            self::write($path, $skill['contents']);
            $result['installed'][] = $name;
        }

        return $result;
    }

    private static function write(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }
}
