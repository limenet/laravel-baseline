import semver from 'semver'

/**
 * True when every version the constraint allows is >= the floor — i.e. it never
 * intersects "< floor". Unparseable input is treated as not guaranteeing it,
 * matching the PHP runner's handling of Composer\Semver's UnexpectedValueException.
 */
export function guaranteesAtLeast(constraint: string, floor: string): boolean {
    try {
        return !semver.intersects(constraint, `<${floor}`, { loose: true })
    } catch {
        return false
    }
}

/** True when the two constraints have at least one version in common. */
export function compatible(a: string, b: string): boolean {
    try {
        return semver.intersects(a, b, { loose: true })
    } catch {
        return false
    }
}

/** The major of a constraint's lowest allowed version, or null if unparseable. */
export function lowestMajor(constraint: string): string | null {
    try {
        const lowest = semver.minVersion(constraint, { loose: true })

        return lowest === null ? null : String(lowest.major)
    } catch {
        return null
    }
}
