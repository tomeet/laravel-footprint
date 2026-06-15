<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 用户模型配置
    |--------------------------------------------------------------------------
    */
    'uuids' => false,
    'user_model' => \App\Models\User::class,
    'user_foreign_key' => 'user_id',

    /*
    |--------------------------------------------------------------------------
    | Footprint 模型配置
    |--------------------------------------------------------------------------
    */
    'footprint_table' => 'footprints',
    'footprint_model' => \Tomeet\Laravel\Footprint\Footprint::class,

    /*
    |--------------------------------------------------------------------------
    | 认证 Guard 配置（API 场景关键）
    |--------------------------------------------------------------------------
    | 支持的认证 guard 列表，按优先级检测
    */
    'guards' => ['web', 'api', 'sanctum'],

    /*
    |--------------------------------------------------------------------------
    | 中间件配置
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        // 是否启用中间件自动记录
        'enabled' => true,

        // Web 中间件组自动附加
        'auto_attach_web' => false,
        'web_route_patterns' => [
            'products.show',
            'posts.show',
            'articles.*',
        ],

        // API 中间件组自动附加 ⭐
        'auto_attach_api' => false,
        'api_route_patterns' => [
            'api.products.show',
            'api.posts.show',
            'api.articles.*',
        ],

        // 通用排除路由
        'except_routes' => [
            'admin.*',
            'api.auth.*',
            'api.upload.*',
            'auth.*',
            'password.*',
        ],

        // 是否只记录已认证用户
        'auth_only' => true,

        // 异步记录方式：afterResponse / queue / sync
        'async' => 'afterResponse',
    ],

    /*
    |--------------------------------------------------------------------------
    | 队列配置（API 场景推荐）
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => true,
        'connection' => env('QUEUE_CONNECTION', 'sync'),
        'queue' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | 路由参数映射
    |--------------------------------------------------------------------------
    */
    'route_parameters' => [
        'products.show' => 'product',
        'posts.show' => 'post',
        'articles.show' => 'article',
        'api.products.show' => 'product',
        'api.posts.show' => 'post',
        'api.articles.show' => 'article',
    ],

    /*
    |--------------------------------------------------------------------------
    | 数据清理
    |--------------------------------------------------------------------------
    */
    'prune' => [
        'enabled' => true,
        'days' => 30,
    ],

];
