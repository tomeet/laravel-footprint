<?php

namespace Tomeet\Laravel\Footprint;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Tomeet\Laravel\Footprint\Http\Middleware\TrackFootprint;

class FootprintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/footprint.php' => config_path('footprint.php'),
            __DIR__.'/../migrations/' => database_path('migrations'),
        ], 'footprint');

        $this->registerMiddleware();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/footprint.php', 'footprint');
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        // 注册中间件别名
        $router->aliasMiddleware('footprint', TrackFootprint::class);

        // Web 组自动附加
        if (config('footprint.middleware.auto_attach_web', false)) {
            if (method_exists($router, 'pushMiddlewareToGroup')) {
                $router->pushMiddlewareToGroup('web', TrackFootprint::class);
            }
        }

        // API 组自动附加 ⭐
        if (config('footprint.middleware.auto_attach_api', false)) {
            if (method_exists($router, 'pushMiddlewareToGroup')) {
                $router->pushMiddlewareToGroup('api', TrackFootprint::class);
            }
        }
    }
}
