import { expect, it } from 'vitest'
import { Policy } from '../src/policy.js'

/**
 * `policy()` caches one Policy for the whole process, so an accessor that handed
 * back the parsed structure itself would let one check's local edit rewrite the
 * standard for every check that runs after it.
 */
it('returns a copy of a string list, so appending to the result cannot extend the policy', () => {
    const policy = Policy.fromObject({ claude: { deny: { shared: ['Read(./.env)'] } } })

    policy.strings('claude.deny.shared').push('Read(./.env.staging)')

    expect(policy.strings('claude.deny.shared')).toStrictEqual(['Read(./.env)'])
})

it('returns a copy of a string map too', () => {
    const policy = Policy.fromObject({ npm: { npmrc: { 'engine-strict': 'true' } } })

    policy.stringMap('npm.npmrc')['min-release-age'] = '7'

    expect(policy.stringMap('npm.npmrc')).toStrictEqual({ 'engine-strict': 'true' })
})

it('normalises a single accepted value into a list, and copies the lists it hands back', () => {
    const policy = Policy.fromObject({
        ci: { requiredJobs: { php: { build: '.build', test: ['.test', '.test_db'] } } },
    })

    const map = policy.stringListMap('ci.requiredJobs.php')

    expect(map).toStrictEqual({ build: ['.build'], test: ['.test', '.test_db'] })

    for (const templates of Object.values(map)) {
        templates.push('.test_anything')
    }

    expect(policy.stringListMap('ci.requiredJobs.php')).toStrictEqual({
        build: ['.build'],
        test: ['.test', '.test_db'],
    })
})

it('rejects an accepted-template list holding a non-string', () => {
    const policy = Policy.fromObject({ ci: { requiredJobs: { php: { test: [1] } } } })

    expect(() => policy.stringListMap('ci.requiredJobs.php')).toThrow(
        'is not a map of strings or lists of strings',
    )
})
