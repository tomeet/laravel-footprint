# Laravel Footprint

👣 User footprint (browsing history) feature for Laravel Application.

## Features

- 🎯 **Passive Tracking** - Automatically record user browsing history via middleware
- 🔄 **Update on Revisit** - Same object revisits update the existing record, keeping the list fresh
- 📸 **Data Snapshot** - Save title, image, and metadata at browse time (survives object deletion)
- 🔒 **Multi-Guard Support** - Works with web session, Sanctum, JWT, and custom guards
- ⚡ **Async Recording** - Supports after-response dispatch or queue jobs
- 🧩 **Polymorphic** - Track any Eloquent model (Product, Post, Article, etc.)

## Installation

```bash
composer require tomeet/laravel-footprint
```

### Publish Configuration & Migrations

```bash
php artisan vendor:publish --provider="Tomeet\Laravel\Footprint\FootprintServiceProvider"
```

### Run Migrations

```bash
php artisan migrate
```

## Configuration

```php

// config/footprint.php
return [
    'uuids' => false,
    'user_model' => App\Models\User::class,
    'user_foreign_key' => 'user_id',
    'footprint_table' => 'footprints',
    'footprint_model' => \Tomeet\Laravel\Footprint\Footprint::class,

    // Supported authentication guards
    'guards' => ['web', 'api', 'sanctum'],

    'middleware' => [
        'enabled' => true,
        'auto_attach_web' => false,
        'auto_attach_api' => false,
        'web_route_patterns' => ['products.show', 'posts.show'],
        'api_route_patterns' => ['api.products.show', 'api.posts.show'],
        'except_routes' => ['admin.*', 'api.auth.*'],
        'auth_only' => true,
        'async' => 'afterResponse', // afterResponse | queue | sync
    ],

    'queue' => [
        'enabled' => true,
        'connection' => env('QUEUE_CONNECTION', 'sync'),
        'queue' => 'default',
    ],

    'route_parameters' => [
        'products.show' => 'product',
        'posts.show' => 'post',
        'api.products.show' => 'product',
        'api.posts.show' => 'post',
    ],

    'prune' => [
        'enabled' => true,
        'days' => 30,
    ],
];
```

## Setup

### 1. Add `Footprinter` trait to your User model

```php
use Tomeet\Laravel\Footprint\Traits\Footprinter;

class User extends Authenticatable
{
    use Footprinter;
}
```

### 2. Add `Footprintable` trait to models you want to track

```php
use Tomeet\Laravel\Footprint\Traits\Footprintable;

class Product extends Model
{
    use Footprintable;

    // Optional: customize snapshot metadata
    public function getFootprintMeta(): array
    {
        return [
            'price' => $this->price,
            'original_price' => $this->original_price,
            'category_id' => $this->category_id,
        ];
    }
}
```

## Usage

### Automatic Tracking via Middleware (Recommended)

#### Route-level middleware

```php
// routes/web.php
Route::middleware(['auth', 'footprint'])->group(function () {
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
});

// routes/api.php
Route::middleware(['auth:sanctum', 'footprint'])->group(function () {
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('api.products.show');
});
```

#### Auto-attach via configuration

```php
// config/footprint.php
'middleware' => [
    'auto_attach_web' => true,
    'auto_attach_api' => true,
    'route_patterns' => ['*.show'],
],
```

### Manual Tracking (when middleware is disabled)

```php
// config/footprint.php
'middleware' => ['enabled' => false]

// In your controller
public function show(Product $product)
{
    auth()->user()?->recordFootprint($product);
    return response()->json($product);
}
```

## API

### User (Footprinter)

```php
$user = User::find(1);

// Record a footprint manually
$user->recordFootprint($product);

// Get paginated footprints
$user->footprintPaginate(20);

// Get recent footprints
$user->recentFootprints(10);

// Get footprint items as a query builder
$user->getFootprintItems(Product::class)->where('status', 'active')->get();

// Check if user has viewed an object
$user->hasFootprinted($product);

// Get specific footprint record
$user->getFootprintOf($product);

// Clear all footprints
$user->clearFootprints();

// Clear footprints by type
$user->clearFootprintsByType(Product::class);

// Attach has_footprinted status to a collection (avoids N+1)
$products = Product::paginate(20);
$user->attachFootprintStatus($products);
```

### Model (Footprintable)

```php
$product = Product::find(1);

// Get all footprints for this product
$product->footprints;

// Get users who viewed this product
$product->footprinters;

// Count unique viewers
$product->uniqueFootprintersCount();

// Total footprint records
$product->totalFootprintsCount();

// Check if a specific user viewed
$product->hasBeenFootprintedBy($user);

// Get recent viewers
$product->recentFootprinters(10);
```

## JSON API Example

```php
class FootprintController extends Controller
{
    public function index(Request $request)
    {
        $footprints = $request->user()->footprintPaginate(20);

        return response()->json([
            'data' => $footprints->map(fn ($fp) => [
                'id' => $fp->id,
                'title' => $fp->title,
                'image' => $fp->image,
                'meta' => $fp->meta,
                'viewed_at' => $fp->viewed_at->toIso8601String(),
                'viewed_at_human' => $fp->viewed_at->diffForHumans(),
                'is_available' => $fp->footprintable !== null,
                'footprintable' => $fp->footprintable?->only(['id', 'price']),
            ]),
            'meta' => [
                'current_page' => $footprints->currentPage(),
                'last_page' => $footprints->lastPage(),
                'per_page' => $footprints->perPage(),
                'total' => $footprints->total(),
            ],
        ]);
    }
}
```

## Pruning Old Records

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('model:prune', [
        '--model' => [\Tomeet\Laravel\Footprint\Footprint::class],
    ])->daily();
}
```

Or manually:

```bash
php artisan model:prune --model="Tomeet\Laravel\Footprint\Footprint"
```

## Why Middleware Instead of Manual Calls?

| | Favorite (Active) | Footprint (Passive) |
|---|---|---|
| Trigger | User clicks "Favorite" button | User visits a page |
| Intent | Explicit action | Implicit behavior |
| Best Practice | Manual call in controller | Middleware auto-capture |
| Risk of Missing | Low (developer chooses) | High (easy to forget) |

Footprint is designed to **not require any code changes in your controllers**.

## Related Packages

- [overtrue/laravel-favorite](https://github.com/overtrue/laravel-favorite) - User favorite feature
- [overtrue/laravel-follow](https://github.com/overtrue/laravel-follow) - User follow feature
- [overtrue/laravel-like](https://github.com/overtrue/laravel-like) - User like feature

## License

MIT
