import { expect, it } from 'vitest'
import { parseDocument } from 'yaml'
import { yamlStyle } from '../src/support/yaml-style.js'

function roundTrip(contents: string): string {
    return parseDocument(contents).toString(yamlStyle(contents))
}

it.each([
    ['two spaces, indented lists', 'job:\n  script:\n    - a\n  variables:\n    k: v\n'],
    ['two spaces, flush lists', 'job:\n  script:\n  - a\n  variables:\n    k: v\n'],
    ['four spaces, indented lists', 'job:\n    script:\n        - a\n    variables:\n        k: v\n'],
    ['only top-level flush lists', 'stages:\n- build\n- test\n'],
    ['only top-level indented lists', 'stages:\n    - build\n    - test\n'],
    ['a list before the first nested map', 'stages:\n- build\njob:\n  script:\n  - a\n'],
    ['unpadded flow collections', 'job:\n  tags: [a, b]\n  vars: {k: v}\n'],
    ['padded flow collections', 'job:\n  tags: [ a, b ]\n'],
    ['a line longer than 80 characters', `job:\n  script:\n    - echo ${'x'.repeat(120)}\n`],
])('writes a parsed file back unchanged: %s', (_name, contents) => {
    expect(roundTrip(contents)).toBe(contents)
})

it('keeps the layout around an edit', () => {
    const contents = 'stages:\n    - build\n\njob:\n    script:\n        - npm ci\n'
    const document = parseDocument(contents)

    document.setIn(['variables', 'NODE_VERSION'], 'latest')

    expect(document.toString(yamlStyle(contents))).toBe(
        'stages:\n    - build\n\njob:\n    script:\n        - npm ci\nvariables:\n    NODE_VERSION: latest\n',
    )
})
