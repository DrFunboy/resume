<?php

use App\Console\ScheduleHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ForceJsonResponse;
use App\Exceptions\ExceptionHandler;
use App\Http\Middleware\HasDomain;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        #commands: __DIR__.'/../routes/console.php',
        #health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(
            prepend: [ForceJsonResponse::class] //должен выполняться первым
        );
        $middleware->alias([
            'HasDomain' => HasDomain::class,
        ]);
    })
    ->withSchedule(new ScheduleHandler())
    ->withCommands([__DIR__ . '/../app/Console/Commands'])
    ->withExceptions(new ExceptionHandler())
    ->create();
