import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { CommentCollector } from '../src/checks/check.js'
import type { ClaudeSettings } from '../src/checks/claude-settings-check.js'
import { DeniesEnvReadsInClaudeSettingsCheck } from '../src/checks/denies-env-reads-in-claude-settings-check.js'
import { Project } from '../src/project.js'

function scratch(): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-claude-')))

    project.write('.env.staging.encrypted', 'ciphertext\n')
    project.writeJson('.claude/settings.json', { permissions: { deny: ['Read(./.env)'] } })

    return project
}

it('denies the environment behind an encrypted file exactly once', () => {
    const project = scratch()
    const check = new DeniesEnvReadsInClaudeSettingsCheck(project, new CommentCollector())

    // `baseline check --fix` runs the dry pass before the repair; both derive the
    // required list from the process-wide policy, so a derivation that mutated it
    // would write the environment twice.
    expect(check.check()).toBe('fail')
    expect(check.fix()).toBe('pass')

    expect(project.readJson<ClaudeSettings>('.claude/settings.json')?.permissions?.deny).toStrictEqual([
        'Read(./.env)',
        'Read(./.env.staging)',
    ])
})
