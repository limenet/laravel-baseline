import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, it } from 'vitest'

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..')

function manifest(name: string): { version?: string; files?: string[] } {
    return JSON.parse(readFileSync(join(root, name), 'utf8'))
}

it('versions the npm and composer packages in lockstep', () => {
    // policy/policy.json is consumed verbatim at runtime by both artifacts and
    // there is no dependency edge that could express a compatibility range
    // between them, so "same version => same policy" is the only guarantee
    // available. @release-it/bumper writes both files; this catches a drift
    // introduced by hand before `npm run release` publishes it.
    expect(manifest('package.json').version).toBe(manifest('composer.json').version)
})

it('ships policy/ to npm as well as to composer', () => {
    expect(manifest('package.json').files).toContain('policy')
})

it('never publishes the npm package privately', () => {
    // `private: true` would silently make every publish a no-op.
    expect(manifest('package.json')).not.toHaveProperty('private')
})
