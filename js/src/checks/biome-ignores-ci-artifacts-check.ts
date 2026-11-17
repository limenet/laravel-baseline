import { policy } from '../policy.js'
import { BiomeIgnoresCheck } from './biome-ignores-check.js'

/**
 * Biome must not check the files a CI runner leaves in the project root. The
 * GitLab runner extracts a `metadata.json` holding the cache key next to the
 * checkout whenever it restores a cache; a `files.includes` that starts from
 * `**` picks it up, and `biome ci` fails on a file nobody committed — but only
 * on the runs that happened to restore a cache.
 */
export class BiomeIgnoresCiArtifactsCheck extends BiomeIgnoresCheck {
    static override readonly checkName = 'biomeIgnoresCiArtifacts'

    protected ignored(): string[] {
        return policy().strings('biome.ciArtifacts')
    }

    protected describe(): string {
        return 'CI runner artifacts'
    }

    protected override consequence(): string {
        return 'failing `biome ci` whenever the runner restores a cache'
    }
}
