import { existsSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * The policy values shared verbatim with the PHP runner (limenet/laravel-baseline).
 * Both read policy/policy.json, so a version floor or a required key is bumped in
 * exactly one place.
 *
 * Accessors narrow to concrete types and throw otherwise, mirroring
 * src/Policy/Policy.php so the two runners stay legible side by side.
 */
export class PolicyError extends Error {
    static wrongType(key: string, expected: string): PolicyError {
        return new PolicyError(`Policy key "${key}" is not a ${expected}`)
    }
}

export class Policy {
    constructor(
        private readonly data: unknown,
        private readonly baseDirectory: string,
    ) {}

    /**
     * The shipped policy directory. `js/src` and `js/dist` sit at the same depth,
     * so this resolves identically under vitest and in the published package.
     */
    static defaultDirectory(): string {
        return join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'policy')
    }

    static fromDirectory(directory = Policy.defaultDirectory()): Policy {
        const file = join(directory, 'policy.json')

        if (!existsSync(file)) {
            throw new PolicyError(
                `Policy file "${file}" is missing or unreadable. It ships with the package — check the "files" whitelist in package.json.`,
            )
        }

        return new Policy(JSON.parse(readFileSync(file, 'utf8')), directory)
    }

    static fromObject(data: unknown, baseDirectory = ''): Policy {
        return new Policy(data, baseDirectory)
    }

    number(key: string): number {
        const value = this.lookup(key)

        if (typeof value !== 'number' || !Number.isInteger(value)) {
            throw PolicyError.wrongType(key, 'integer')
        }

        return value
    }

    string(key: string): string {
        const value = this.lookup(key)

        if (typeof value !== 'string') {
            throw PolicyError.wrongType(key, 'string')
        }

        return value
    }

    boolean(key: string): boolean {
        const value = this.lookup(key)

        if (typeof value !== 'boolean') {
            throw PolicyError.wrongType(key, 'boolean')
        }

        return value
    }

    strings(key: string): string[] {
        const value = this.lookup(key)

        if (!Array.isArray(value) || value.some((item) => typeof item !== 'string')) {
            throw PolicyError.wrongType(key, 'list of strings')
        }

        return value as string[]
    }

    stringMap(key: string): Record<string, string> {
        const value = this.lookup(key)

        if (typeof value !== 'object' || value === null || Array.isArray(value)) {
            throw PolicyError.wrongType(key, 'map of strings')
        }

        for (const item of Object.values(value)) {
            if (typeof item !== 'string') {
                throw PolicyError.wrongType(key, 'map of strings')
            }
        }

        return value as Record<string, string>
    }

    /**
     * The verbatim body of a canonical file under policy/templates/. Multi-line
     * bodies live there rather than JSON-escaped into policy.json, so their diffs
     * stay readable and their newlines survive both loaders unchanged.
     */
    template(name: string): string {
        const file = join(this.baseDirectory, 'templates', name)

        if (!existsSync(file)) {
            throw new PolicyError(`Policy template "${file}" is missing or unreadable.`)
        }

        return readFileSync(file, 'utf8')
    }

    private lookup(key: string): unknown {
        let value: unknown = this.data

        for (const segment of key.split('.')) {
            if (typeof value !== 'object' || value === null || !(segment in value)) {
                throw new PolicyError(`Policy key "${key}" is not defined in policy/policy.json`)
            }

            value = (value as Record<string, unknown>)[segment]
        }

        return value
    }
}

let cached: Policy | undefined

/** The shipped policy, read once per process. */
export function policy(): Policy {
    cached ??= Policy.fromDirectory()

    return cached
}

/** Test seam: swap the process-wide policy, or reset it by passing nothing. */
export function setPolicy(replacement?: Policy): void {
    cached = replacement
}
