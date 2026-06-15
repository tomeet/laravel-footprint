<?php

namespace Tomeet\Laravel\Footprint\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

trait TrackFootprint
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request)) {
            $this->track($request);
        }

        return $response;
    }

    /**
     * 判断是否满足记录条件
     */
    protected function shouldTrack(Request $request): bool
    {
        if (!config('footprint.middleware.enabled', true)) {
            return false;
        }

        if (config('footprint.middleware.auth_only', true) && !$this->resolveUser()) {
            return false;
        }

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return false;
        }

        // 排除列表
        foreach (config('footprint.middleware.except_routes', []) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return false;
            }
        }

        // 根据中间件组选择对应的路由模式
        $group = $this->getMiddlewareGroup($request);
        $patterns = match ($group) {
            'api' => config('footprint.middleware.api_route_patterns', []),
            'web' => config('footprint.middleware.web_route_patterns', []),
            default => config('footprint.middleware.route_patterns', []),
        };

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 执行记录
     */
    protected function track(Request $request): void
    {
        $user = $this->resolveUser();
        if (!$user || !method_exists($user, 'recordFootprint')) {
            return;
        }

        $object = $this->resolveTrackable($request);
        if (!$object) {
            return;
        }

        $snapshot = $this->buildSnapshot($object);

        $async = config('footprint.middleware.async', 'afterResponse');

        match ($async) {
            'queue' => $this->trackViaQueue($user, $object, $snapshot),
            'afterResponse' => $this->trackAfterResponse($user, $object, $snapshot),
            default => $user->recordFootprint($object, $snapshot),
        };
    }

    /**
     * 通过队列异步记录（API 推荐）
     */
    protected function trackViaQueue(Model $user, Model $object, array $snapshot): void
    {
        dispatch(new \Tomeet\Laravel\Footprint\Jobs\RecordFootprintJob($user, $object, $snapshot))
            ->onConnection(config('footprint.queue.connection'))
            ->onQueue(config('footprint.queue.queue'));
    }

    /**
     * 响应后异步记录
     */
    protected function trackAfterResponse(Model $user, Model $object, array $snapshot): void
    {
        dispatch(function () use ($user, $object, $snapshot) {
            try {
                $user->recordFootprint($object, $snapshot);
            } catch (\Exception $e) {
                \Log::warning('Footprint record failed: ' . $e->getMessage());
            }
        })->afterResponse();
    }

    /**
     * 解析可追踪的模型
     */
    protected function resolveTrackable(Request $request): ?Model
    {
        $routeName = $request->route()->getName();

        // 优先从配置映射获取
        $paramMap = config('footprint.route_parameters', []);
        if (isset($paramMap[$routeName])) {
            return $request->route($paramMap[$routeName]);
        }

        // 自动推断
        foreach ($request->route()->parameters() as $param) {
            if ($param instanceof Model) {
                return $param;
            }
        }

        return null;
    }

    /**
     * 构建快照数据
     */
    protected function buildSnapshot(Model $object): array
    {
        return [
            'title' => $object->title ?? $object->name ?? null,
            'image' => $object->image ?? $object->thumb ?? null,
            'meta' => method_exists($object, 'getFootprintMeta')
                ? $object->getFootprintMeta()
                : null,
        ];
    }

    /**
     * 解析当前认证用户（支持多 guard）
     */
    protected function resolveUser(): ?Authenticatable
    {
        foreach (config('footprint.guards', ['web']) as $guard) {
            if ($user = auth($guard)->user()) {
                return $user;
            }
        }

        return null;
    }

    /**
     * 判断请求属于哪个中间件组
     */
    protected function getMiddlewareGroup(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $middleware = $route->gatherMiddleware();

        foreach ($middleware as $m) {
            if (str_contains($m, 'api')) {
                return 'api';
            }
            if (str_contains($m, 'web')) {
                return 'web';
            }
        }

        return null;
    }
}
