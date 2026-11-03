<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // حارس دخول الطلاب (برقم الهوية) منفصل عن دخول المدرسين والإدارة
        config([
            'auth.providers.students' => ['driver' => 'eloquent', 'model' => \App\Models\Student::class],
            'auth.guards.student' => ['driver' => 'session', 'provider' => 'students'],
        ]);

        // في الواجهات: @perm('teachers', 'edit') ... @endperm
        Blade::if('perm', fn (string $module, string $level = 'view') => (bool) auth()->user()?->hasPermission($module, $level));
    }
}
