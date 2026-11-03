<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'perm' => \App\Http\Middleware\EnsurePermission::class,
            'student.ready' => \App\Http\Middleware\StudentPasswordChanged::class,
        ]);
        $middleware->redirectGuestsTo(fn ($request) => $request->is('student', 'student/*') ? route('student.login') : route('login'));
        $middleware->redirectUsersTo(fn ($request) => $request->is('student', 'student/*') ? route('student.home') : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
