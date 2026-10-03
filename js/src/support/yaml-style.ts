import type { ToStringOptions } from 'yaml'

const KEY_OPENING_BLOCK = /^[^\s#-][^#]*:\s*(#.*)?$/
const ITEM = /^-(\s|$)/

/**
 * Stringify options that reproduce how a YAML file is already laid out, for
 * writing a parsed document back without reformatting the parts a fix did not
 * touch. parseDocument() keeps comments and scalar styles, but toString() lays
 * out indentation, sequences and flow collections from its own defaults.
 *
 * - indent: how far the first nested key sits below a key that opens a block.
 * - indentSeq: whether a block sequence sits deeper than its key. The yaml
 *   library places the `- ` either a full indent deeper or `indent - 2`
 *   deeper, so a file with its dashes flush under a four-space key comes out
 *   two deeper; with a two-space indent both layouts round-trip exactly.
 * - flowCollectionPadding: `[a, b]` or `[ a, b ]`.
 * - lineWidth: never fold. The source line length is the project's choice, and
 *   folding a long plain `script:` entry splits a shell command over two lines.
 */
export function yamlStyle(contents: string): ToStringOptions {
    let mapOffset: number | undefined
    let seqOffset: number | undefined
    let parent: { width: number; opensBlock: boolean } | null = null

    for (const line of contents.split('\n')) {
        const content = line.trimStart()

        if (content === '' || content.startsWith('#')) {
            continue
        }

        const width = line.length - content.length
        const isItem = ITEM.test(content)

        if (parent?.opensBlock && width >= parent.width) {
            if (isItem) {
                seqOffset ??= width - parent.width
            } else if (width > parent.width) {
                mapOffset ??= width - parent.width
            }
        }

        parent = { width, opensBlock: !isItem && KEY_OPENING_BLOCK.test(content) }

        if (mapOffset !== undefined && seqOffset !== undefined) {
            break
        }
    }

    // A file with only top-level lists (`stages:` and nothing nested) still
    // says how deep it indents through them.
    const indent = mapOffset ?? (seqOffset !== undefined && seqOffset > 0 ? seqOffset : 2)
    const padded = /[:-]\s+[[{] /.test(contents)
    const unpadded = /[:-]\s+[[{][^\s\]}]/.test(contents)

    return {
        indent,
        indentSeq: seqOffset === undefined || seqOffset >= indent,
        flowCollectionPadding: padded || !unpadded,
        lineWidth: 0,
    }
}
