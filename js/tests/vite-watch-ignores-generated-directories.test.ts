import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { CommentCollector } from '../src/checks/check.js'
import { ViteWatchIgnoresGeneratedDirectoriesCheck } from '../src/checks/vite-watch-ignores-generated-directories-check.js'
import { policy } from '../src/policy.js'
import { Project } from '../src/project.js'
import {
    append,
    configObject,
    hasOpaqueMembers,
    type JsNode,
    membersNamed,
} from '../src/support/js-object-literal.js'

const GLOBS = policy().strings('vite.watchIgnored.shared')

function scratch(files: Record<string, string>): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-vite-')))

    for (const [path, contents] of Object.entries(files)) {
        project.write(path, contents)
    }

    return project
}

function run(project: Project) {
    const comments = new CommentCollector()

    return { check: new ViteWatchIgnoresGeneratedDirectoriesCheck(project, comments), comments }
}

function valueAt(config: JsNode | null, key: string): JsNode {
    const value = config === null ? null : membersNamed(config, key)[0]?.value

    if (value === null || value === undefined) {
        throw new Error(`No value for ${key}`)
    }

    return value
}

describe('the config scanner', () => {
    it.each([
        ['bare object', "export default { base: '/app/' }"],
        [
            'defineConfig',
            "import { defineConfig } from 'vite';\n\nexport default defineConfig({ base: '/app/' });",
        ],
        ['arrow function', "export default defineConfig(({ mode }) => ({ base: '/app/' }))"],
        ['async arrow function', "export default defineConfig(async (env) => ({ base: '/app/' }))"],
        ['single parameter', "export default defineConfig(env => ({ base: '/app/' }))"],
        ['quoted key', 'export default { "base": \'/app/\' }'],
        ['after other exports', "export const a = { base: 'x' };\nexport default { base: '/app/' }"],
        [
            'comments around',
            "/* export default { base: 'no' } */\nexport default /* here */ { // why\n base: '/app/' }",
        ],
    ])('finds the exported object literal: %s', (_, source) => {
        expect(valueAt(configObject(source), 'base').value).toBe('/app/')
    })

    it.each([
        ['empty', ''],
        ['no default export', "export const config = { base: '/' }"],
        ['a variable', "const config = { base: '/' };\nexport default config;"],
        ['block-bodied function', "export default defineConfig(() => { return { base: '/' } })"],
        ['another helper', "export default mergeConfig(base, { base: '/' })"],
        ['a type assertion', "export default { base: '/' } satisfies UserConfig"],
        ['commonjs', "module.exports = { base: '/' }"],
        ['unterminated', "export default { base: '/'"],
        ['nested export', "function f() { export default { base: '/' } }"],
    ])('does not follow what it cannot read off the source: %s', (_, source) => {
        expect(configObject(source)).toBeNull()
    })

    it('keeps the offsets and kinds of members', () => {
        const source =
            "export default { plugins: [vue(), 'x'], server: { port: 5173 }, name, ...rest, [key]: 1, run() {} }"
        const config = configObject(source)
        const plugins = valueAt(config, 'plugins')

        expect(config?.members.map((member) => member.key)).toEqual([
            'plugins',
            'server',
            'name',
            null,
            null,
            null,
        ])
        expect(source.slice(plugins.start, plugins.end)).toBe("[vue(), 'x']")
        expect(plugins.elements.map((element) => element.kind)).toEqual(['other', 'string'])
        expect(valueAt(config, 'server').kind).toBe('object')
        expect(config && membersNamed(config, 'name')[0]?.value).toBeNull()
        expect(config && hasOpaqueMembers(config)).toBe(true)
    })

    it('treats a literal that is operated on as an expression', () => {
        const config = configObject(
            // biome-ignore lint/suspicious/noTemplateCurlyInString: the placeholder is the input under test
            "export default { a: ['x'].concat(y), b: 'x' + y, c: `${y}/x`, d: `plain`, e: { f: 1 }.f }",
        )

        expect(['a', 'b', 'c', 'd', 'e'].map((key) => valueAt(config, key).kind)).toEqual([
            'other',
            'other',
            'other',
            'string',
            'other',
        ])
    })

    it('is not thrown off by brackets and quotes inside strings, templates, regexes and comments', () => {
        const config = configObject(
            [
                'export default {',
                "    a: '}{', // }",
                // biome-ignore lint/suspicious/noTemplateCurlyInString: the placeholder is the input under test
                "    b: `${fn({ x: '}' })} }`,",
                '    c: /[/\'"}]+\\/}/g,',
                '    d: 4 / 2, /* } */',
                "    e: (x) => { return x['}'] },",
                "    last: 'yes',",
                '}',
            ].join('\n'),
        )

        expect(config?.members).toHaveLength(6)
        expect(valueAt(config, 'last').value).toBe('yes')
    })

    it.each([
        [
            'trailing comma',
            "export default {\n  a: [\n    'x',\n  ],\n}",
            "export default {\n  a: [\n    'x',\n    'new',\n  ],\n}",
        ],
        [
            'no trailing comma',
            "export default {\n  a: [\n    'x'\n  ],\n}",
            "export default {\n  a: [\n    'x',\n    'new'\n  ],\n}",
        ],
        [
            'comment after the comma',
            "export default {\n  a: [\n    'x', // why\n  ],\n}",
            "export default {\n  a: [\n    'x', // why\n    'new',\n  ],\n}",
        ],
        [
            'comment without a comma',
            "export default {\n  a: [\n    'x' // why\n  ],\n}",
            "export default {\n  a: [\n    'x', // why\n    'new'\n  ],\n}",
        ],
        [
            'closing bracket on the last line',
            "export default {\n  a: [\n    'x']\n}",
            "export default {\n  a: [\n    'x',\n    'new']\n}",
        ],
        ['single line', "export default { a: ['x'] }", "export default { a: ['x', 'new'] }"],
        ['empty', 'export default {\n  a: [],\n}', "export default {\n  a: [\n    'new',\n  ],\n}"],
        [
            'empty across lines',
            'export default {\n  a: [\n  ],\n}',
            "export default {\n  a: [\n    'new',\n  ],\n}",
        ],
        [
            'empty but for a comment',
            'export default {\n  a: [/* none yet */],\n}',
            "export default {\n  a: [/* none yet */\n    'new',\n  ],\n}",
        ],
        [
            'tabs',
            "export default {\n\ta: [\n\t\t'x',\n\t],\n}",
            "export default {\n\ta: [\n\t\t'x',\n\t\t'new',\n\t],\n}",
        ],
    ])('appends in the style of the last member: %s', (_, before, after) => {
        expect(append(before, valueAt(configObject(before), 'a'), '  ', () => "'new'")).toBe(after)
    })
})

describe('viteWatchIgnoresGeneratedDirectories', () => {
    it('passes when the project has no Vite config', () => {
        expect(run(scratch({ 'package.json': '{}\n' })).check.check()).toBe('pass')
    })

    it('requires only the shared directories', () => {
        const entries = GLOBS.map((glob) => `"${glob}"`).join(', ')
        const project = scratch({
            'vite.config.ts': `export default defineConfig({ server: { watch: { ignored: [${entries}, /\\.tmp$/, ...more] } } })`,
        })

        expect(run(project).check.check()).toBe('pass')
    })

    it('names what is missing', () => {
        const { check, comments } = run(
            scratch({
                'vite.config.js': "export default { server: { watch: { ignored: ['**/coverage/**'] } } }",
            }),
        )

        expect(check.check()).toBe('fail')
        expect(comments.all()).toEqual([
            "Missing in vite.config.js: server.watch.ignored must list '**/playwright-report/**', '**/.trivycache/**', so the dev server does not watch generated directories",
        ])
    })

    it('reads the config Vite would load', () => {
        // Vite resolves vite.config.js before vite.config.ts.
        const { check, comments } = run(
            scratch({
                'vite.config.ts': `export default { server: { watch: { ignored: [${GLOBS.map((glob) => `'${glob}'`).join(', ')}] } } }`,
                'vite.config.js': 'export default {}',
            }),
        )

        expect(check.check()).toBe('fail')
        expect(comments.all()[0]).toMatch(/^Missing in vite\.config\.js/)
    })

    it.each([
        ['a variable', 'export default config', 'it does not export a plain object literal'],
        [
            'watch disabled',
            'export default { server: { watch: null } }',
            'server.watch is not an object literal',
        ],
        ['shorthand server', 'export default { server }', 'server is not an object literal'],
        ['server twice', 'export default { server: {}, server: {} }', 'server is declared more than once'],
        [
            'spread at the root',
            'export default { ...base }',
            'server may come from a spread or a computed key',
        ],
        [
            'a single string',
            "export default { server: { watch: { ignored: '**/vendor/**' } } }",
            'server.watch.ignored is not an array literal',
        ],
    ])('leaves a config it cannot follow untouched: %s', (_, source, reason) => {
        const project = scratch({ 'vite.config.js': source })
        const { check, comments } = run(project)

        expect(check.fix()).toBe('fail')
        expect(comments.all()[0]).toContain(`(${reason})`)
        expect(project.read('vite.config.js')).toBe(source)
    })

    it('writes an inline block into a single-line config', () => {
        const project = scratch({ 'vite.config.js': 'export default defineConfig({ plugins: [vue()] })\n' })
        const entries = GLOBS.map((glob) => `'${glob}'`).join(', ')

        expect(run(project).check.fix()).toBe('pass')
        expect(project.read('vite.config.js')).toBe(
            `export default defineConfig({ plugins: [vue()], server: { watch: { ignored: [${entries}] } } })\n`,
        )
    })

    it('indents with tabs where the file does', () => {
        const project = scratch({
            'vite.config.js':
                'export default {\n\tserver: {\n\t\twatch: {\n\t\t\tusePolling: true\n\t\t}\n\t}\n}\n',
        })
        const entries = GLOBS.map((glob) => `\t\t\t\t'${glob}'`).join(',\n')

        expect(run(project).check.fix()).toBe('pass')
        expect(project.read('vite.config.js')).toBe(
            `export default {\n\tserver: {\n\t\twatch: {\n\t\t\tusePolling: true,\n\t\t\tignored: [\n${entries}\n\t\t\t]\n\t\t}\n\t}\n}\n`,
        )
    })
})
