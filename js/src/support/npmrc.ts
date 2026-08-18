/**
 * Parse an .npmrc into a key => value map. Comments (# and ;) and lines without
 * an `=` are skipped, mirroring AbstractCheck::getNpmrc() in the PHP runner.
 */
export function parseNpmrc(contents: string | null): Record<string, string> {
    const config: Record<string, string> = {}

    for (const rawLine of (contents ?? '').split('\n')) {
        const line = rawLine.trim()

        if (line === '' || line.startsWith('#') || line.startsWith(';') || !line.includes('=')) {
            continue
        }

        const separator = line.indexOf('=')

        config[line.slice(0, separator).trim()] = line.slice(separator + 1).trim()
    }

    return config
}

/**
 * Set each key, updating an existing line in place or appending it, so all other
 * lines and comments survive.
 */
export function upsertNpmrc(contents: string | null, entries: Record<string, string>): string {
    const existing = contents ?? ''
    const lines = existing === '' ? [] : existing.replace(/\n+$/, '').split('\n')

    for (const [key, value] of Object.entries(entries)) {
        const line = `${key}=${value}`
        const index = lines.findIndex((candidate) =>
            new RegExp(`^\\s*${key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*=`).test(candidate),
        )

        if (index === -1) {
            lines.push(line)
        } else {
            lines[index] = line
        }
    }

    return `${lines.join('\n')}\n`
}
