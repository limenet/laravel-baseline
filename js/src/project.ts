import { existsSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'

/**
 * The project under inspection. Every file access a check makes goes through
 * here, so checks never touch absolute paths and the whole runner can be pointed
 * at a temporary directory in tests.
 *
 * The PHP runner's equivalent is base_path() plus the file helpers on
 * AbstractCheck.
 */
export class Project {
    constructor(readonly basePath: string) {}

    static at(basePath: string = process.cwd()): Project {
        return new Project(resolve(basePath))
    }

    path(relative: string): string {
        return join(this.basePath, relative)
    }

    exists(relative: string): boolean {
        return existsSync(this.path(relative))
    }

    isDirectory(relative: string): boolean {
        const path = this.path(relative)

        return existsSync(path) && statSync(path).isDirectory()
    }

    read(relative: string): string | null {
        return this.exists(relative) ? readFileSync(this.path(relative), 'utf8') : null
    }

    readJson<T = Record<string, unknown>>(relative: string): T | null {
        const contents = this.read(relative)

        if (contents === null) {
            return null
        }

        // Throws rather than yielding null, as the PHP runner's JSON_THROW_ON_ERROR
        // does: null is how callers spell "the file isn't there", so swallowing a
        // syntax error would let --fix overwrite a file it never managed to read.
        try {
            return JSON.parse(contents) as T
        } catch (error) {
            throw new Error(
                `${relative} is not valid JSON: ${error instanceof Error ? error.message : error}`,
            )
        }
    }

    write(relative: string, contents: string): void {
        const path = this.path(relative)

        mkdirSync(dirname(path), { recursive: true })
        writeFileSync(path, contents)
    }

    /**
     * Four-space indent plus a trailing newline, matching the PHP runner's
     * writePackageJson() so the two never fight over formatting in a project
     * that runs both.
     */
    writeJson(relative: string, data: unknown): void {
        this.write(relative, `${JSON.stringify(data, null, 4)}\n`)
    }

    remove(relative: string): void {
        rmSync(this.path(relative), { recursive: true, force: true })
    }
}
