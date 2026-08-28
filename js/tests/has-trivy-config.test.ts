import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { parse, stringify } from 'yaml'
import { CommentCollector } from '../src/checks/check.js'
import { HasTrivyConfigCheck } from '../src/checks/has-trivy-config-check.js'
import { policy } from '../src/policy.js'
import { Project } from '../src/project.js'

/** A project with everything Trivy needs, so each test can break one thing. */
function scratch(overrides: Record<string, string> = {}): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-trivy-')))
    const files: Record<string, string> = {
        '.gitlab-ci.yml': 'security:\n  extends:\n    - .lint_security\n',
        '.gitignore': '.trivycache/\n',
        '.trivyignore.yaml': '',
        'trivy.yaml': policy().template('trivy.yaml'),
        ...overrides,
    }

    for (const [path, contents] of Object.entries(files)) {
        project.write(path, contents)
    }

    return project
}

function check(project: Project) {
    return new HasTrivyConfigCheck(project, new CommentCollector())
}

function config(project: Project): Record<string, unknown> {
    return parse(project.read('trivy.yaml') ?? '')
}

it('merges the canonical requirements while preserving the project’s own entries', () => {
    const declared = parse(policy().template('trivy.yaml'))
    declared.scan.scanners = ['secret', 'license']
    declared.scan['skip-dirs'].push('cache/')
    delete declared.ignorefile
    delete declared.pkg

    const project = scratch({ 'trivy.yaml': stringify(declared) })

    expect(check(project).fix()).toBe('pass')

    const fixed = config(project) as unknown as {
        scan: { scanners: string[]; 'skip-dirs': string[] }
        ignorefile: string
        pkg: { 'include-dev-deps': boolean }
    }

    expect(fixed.scan.scanners).toContain('license')
    expect(fixed.scan.scanners).toContain('misconfig')
    expect(fixed.scan['skip-dirs']).toContain('cache/')
    expect(fixed.ignorefile).toBe('.trivyignore.yaml')
    expect(fixed.pkg['include-dev-deps']).toBe(true)
})

it('keeps the comments in a hand-written trivy.yaml', () => {
    const project = scratch({
        'trivy.yaml': `# tuned for this project\n${policy().template('trivy.yaml').replace('dependency-tree: true\n', '')}`,
    })

    expect(check(project).fix()).toBe('pass')
    expect(project.read('trivy.yaml')).toContain('# tuned for this project')
})

it('removes a pinned severity instead of accepting it', () => {
    const project = scratch({
        'trivy.yaml': `${policy().template('trivy.yaml')}severity:\n  - CRITICAL\n`,
    })
    const check_ = check(project)

    expect(check_.check()).toBe('fail')
    expect(check_.fix()).toBe('pass')
    expect(config(project)).not.toHaveProperty('severity')
})

it('adds the security job without disturbing the rest of the pipeline', () => {
    const project = scratch({
        '.gitlab-ci.yml': '# frontend pipeline\njs:\n  extends:\n    - .lint_js\n',
    })

    expect(check(project).fix()).toBe('pass')

    const pipeline = project.read('.gitlab-ci.yml') ?? ''

    expect(pipeline).toContain('# frontend pipeline')
    expect(parse(pipeline)).toStrictEqual({
        js: { extends: ['.lint_js'] },
        security: { extends: ['.lint_security'] },
    })
})

it('appends the cache directory to .gitignore exactly once', () => {
    const project = scratch({ '.gitignore': 'node_modules/\n' })

    expect(check(project).fix()).toBe('pass')

    const contents = project.read('.gitignore') ?? ''

    expect(contents).toContain('node_modules/')
    expect(contents.match(/\.trivycache\//g)).toHaveLength(1)
})

it('creates the ignore file when it is absent', () => {
    const project = scratch()
    project.remove('.trivyignore.yaml')

    expect(check(project).check()).toBe('fail')
    expect(check(project).fix()).toBe('pass')
    expect(project.read('.trivyignore.yaml')).toBe('')
})
