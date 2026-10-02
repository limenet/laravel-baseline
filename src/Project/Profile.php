<?php

namespace Limenet\LaravelBaseline\Project;

/**
 * The kind of project a run checks. A check opts into the profiles it means
 * something in (see CheckInterface::profiles()); the Laravel runner always
 * runs the Laravel profile, the standalone runner detects one of the others.
 */
enum Profile: string
{
    case Laravel = 'laravel';
    case Php = 'php';
    case WordPress = 'wordpress';

    /**
     * The policy group for the PHP runner this profile is checked by:
     * artisan for Laravel, vendor/bin/baseline for everything else.
     */
    public function runner(): string
    {
        return $this === self::Laravel ? 'laravel' : 'standalone';
    }
}
