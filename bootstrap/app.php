<?php
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveOrganization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up', then: function(){ RateLimiter::for('login',fn(Request $r)=>Limit::perMinute(5)->by($r->ip().'|'.$r->input('email'))); })
    ->withMiddleware(function (Middleware $middleware): void { $middleware->alias(['organization'=>ResolveOrganization::class,'permission'=>RequirePermission::class]); })
    ->withExceptions(fn (Exceptions $exceptions) => null)->create();
