<?php

use Limenet\LaravelBaseline\Schedule\EnvironmentGuardedScheduleFinder;

function scheduleSourcesContain(array $files, string $command = 'backup:run'): bool
{
    test()->withTempBasePath($files);

    return (new EnvironmentGuardedScheduleFinder)->contains(
        [
            base_path('routes/console.php'),
            base_path('bootstrap/app.php'),
            base_path('app/Console/Kernel.php'),
        ],
        $command,
    );
}

it('finds a Schedule facade entry behind isProduction() in routes/console.php', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (app()->isProduction()) {
    Schedule::command('backup:run')->daily();
}
PHP]))->toBeTrue();
});

it('finds a withSchedule entry behind App::environment() in bootstrap/app.php', function (): void {
    expect(scheduleSourcesContain(['bootstrap/app.php' => <<<'PHP'
<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule): void {
        if (App::environment('production', 'staging')) {
            $schedule->command('backup:run')->daily();
        }
    })
    ->create();
PHP]))->toBeTrue();
});

it('finds a Kernel entry behind a negated isLocal()', function (): void {
    expect(scheduleSourcesContain(['app/Console/Kernel.php' => <<<'PHP'
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        if (!$this->app->isLocal()) {
            $schedule->command('backup:run')->daily();
        }
    }
}
PHP]))->toBeTrue();
});

it('finds an entry in the else branch of an environment guard', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (app()->runningUnitTests()) {
    // nothing
} elseif (app()->isLocal()) {
    // nothing
} else {
    Schedule::command('backup:run')->daily();
}
PHP]))->toBeTrue();
});

it('finds an entry behind a config(app.env) comparison', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if ('production' === config('app.env') && env('APP_ENV') !== 'local') {
    Schedule::command('backup:run')->daily();
}
PHP]))->toBeTrue();
});

it('matches a command with arguments the same way the runtime lookup does', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (app()->isProduction()) {
    Schedule::command('backup:run --only-db')->hourly();
}
PHP]))->toBeTrue();
});

it('does not find an entry behind a condition that is not about the environment', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (config('backup.enabled')) {
    Schedule::command('backup:run')->daily();
}
PHP]))->toBeFalse();
});

it('does not find an entry when an environment guard is combined with another condition', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (app()->isProduction() && config('backup.enabled')) {
    Schedule::command('backup:run')->daily();
}
PHP]))->toBeFalse();
});

it('does not find an entry whose environment guard sits inside another condition', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (false) {
    if (app()->isProduction()) {
        Schedule::command('backup:run')->daily();
    }
}
PHP]))->toBeFalse();
});

it('does not find a commented-out entry', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Schedule;

if (app()->isProduction()) {
    // Schedule::command('backup:run')->daily();
}
PHP]))->toBeFalse();
});

it('does not mistake an Artisan closure command for a schedule entry', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Artisan;

if (app()->isProduction()) {
    Artisan::command('backup:run', function (): void {});
}
PHP]))->toBeFalse();
});

it('skips a file it cannot parse', function (): void {
    expect(scheduleSourcesContain(['routes/console.php' => "<?php\nif (app()->isProduction() {\n    Schedule::command('backup:run');\n"]))->toBeFalse();
});

it('finds nothing when no scheduler sources exist', function (): void {
    expect(scheduleSourcesContain([]))->toBeFalse();
});
