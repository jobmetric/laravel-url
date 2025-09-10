[contributors-shield]: https://img.shields.io/github/contributors/jobmetric/laravel-url.svg?style=for-the-badge
[contributors-url]: https://github.com/jobmetric/laravel-url/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/jobmetric/laravel-url.svg?style=for-the-badge&label=Fork
[forks-url]: https://github.com/jobmetric/laravel-url/network/members
[stars-shield]: https://img.shields.io/github/stars/jobmetric/laravel-url.svg?style=for-the-badge
[stars-url]: https://github.com/jobmetric/laravel-url/stargazers
[license-shield]: https://img.shields.io/github/license/jobmetric/laravel-url.svg?style=for-the-badge
[license-url]: https://github.com/jobmetric/laravel-url/blob/master/LICENCE.md
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-blue.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/majidmohammadian

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![MIT License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

# Url And Slug for laravel Model

It is a package for url and slug storage management in each model that you can use in your Laravel projects.

## Install via composer

Run the following command to pull in the latest version:

```bash
composer require jobmetric/laravel-url
```

## Documentation

This package gives each Eloquent model:

One canonical slug stored in a dedicated `slugs` table (polymorphic one-to-one).
A versioned full URL history stored in the `urls` table (polymorphic one-to-many with soft deletes).

Automatic syncing of URLs when the slug or any parent path segment changes.

Global uniqueness for active full URLs, enforced at the application layer.

Soft-delete/restore support for slugs and URLs, with conflict checks on restore.

>#### Run migrations once after installing the package:

```bash
php artisan migrate
```

### HasUrl Trait

#### 1) Quick Start

##### 1.1 Add the trait and implement `UrlContract`

`HasUrl` relies on your model implementing `UrlContract` (i.e. it must expose how to compute its full URL).

```php
use Illuminate\Database\Eloquent\Model;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\HasUrl;

class Product extends Model implements UrlContract
{
    use HasUrl;

    // Example: build the current full URL from your own fields and relations
    public function getFullUrl(): string
    {
        // e.g. /shop/{categorySlug}/{productSlug}
        $categorySlug = optional($this->category)->slug ?? 'uncategorized';
        $selfSlug     = $this->slug ?? 'product-'.$this->getKey();

        return "/shop/{$categorySlug}/{$selfSlug}";
    }
}
```

>#### Why `UrlContract?`
The package must know the current full path for the model. `getFullUrl()` is your canonical builder for that path.

##### 1.2 Create a record and assign a slug

You don’t set columns on the `urls` table yourself. You only provide a `slug` (optional `slug_collection`) and the package will do the rest.

```php
$product = Product::create([
    'name' => 'MacBook Pro 14',
]);

// Persist a slug and sync the versioned URL
$product->dispatchSlug('macbook-pro-14', 'products');
// collection is optional; see §3.1
```

- The package **slugifies** and **length-limits** your input (100 chars).
- It stores **one** row in slugs (per model).
- It creates **version=1** in urls.
- It guarantees **no active conflict** with another model’s active full URL.

##### 1.3 Read back the slug and current full URL

```php
$product->slug;                 // "macbook-pro-14" (accessor)
$product->slug_resource;        // SlugResource with slug + collection
$product->slug_collection;      // "products" (or your default)

$product->getActiveFullUrl();   // e.g. "/shop/laptops/macbook-pro-14"
```

#### 2) What the package does for you

- **Keeps one slug per model.** (Polymorphic `slugs` table)

- **Tracks full URL history.** (`urls` rows are versioned; active row = highest `version` with `deleted_at NULL`)

- **Auto-version on changes.** If the computed full URL changes, the previous URL is soft-deleted and a new version is inserted.

- **Conflict safety.**

    - Throws `SlugConflictException` if another record uses the same slug in the same collection.

    - Throws `UrlConflictException` if another active record uses the same full URL.

- **Cascade refresh.** If your model’s URL affects children, you can cascade (see §5).

- **Delete/restore aware.** Soft delete removes active slug/URL from public view; restore validates conflicts and re-syncs.

#### 3) Slugs & Collections

##### 3.1 One slug, optional collection

Each model gets exactly one slug row. You may tag it with a collection (e.g. to group slugs by context).

```php
// Set/change slug with an optional collection
$product->dispatchSlug('mbp-14', 'products');
```

If you **omit** the collection:

- If your model defines `getSlugCollectionDefault(): ?string`, it will be used.

- Else, if your model has an attribute `type`, that value becomes the collection.

- Else, collection is `NULL`.

##### 3.2 Reading by collection

```php
// Return the SlugResource envelope for the default collection
$product->slug();

// Return the SlugResource envelope for a specific collection
$product->slugByCollection('products'); 

// Just the slug string (mode=true)
$product->slugByCollection('products', true); // "mbp-14"
```

##### 3.3 Finding models by slug

```php
// Search across all collections for this model type
Product::findBySlug('mbp-14');

// Require a specific collection
Product::findBySlugAndCollection('mbp-14', 'products');

// ...or throw SlugNotFoundException on miss
Product::findBySlugOrFail('mbp-14');
Product::findBySlugAndCollectionOrFail('mbp-14', 'products');
```

##### 3.4 Removing the slug

```php
// Remove slug if (optionally) the collection matches
$product->forgetSlug('products');
```

> **Note:** A model without a slug can still compute a full URL if your `getFullUrl()` does not depend on it, but most setups will.

#### 4) Versioned full URLs

##### 4.1 Automatic syncing

You do **not** call any URL method on normal saves; syncing happens transparently in the trait:

- On `saving`: caches the pre-save full URL.

- On `saved`:
    - upserts the slug (if provided this request),
    - computes the new full URL via `getFullUrl()`,
    - if first time → insert version `1`,
    - if changed → soft-delete previous active row and insert `version+1`,
    - fires `UrlChanged` event with `(model, oldFullUrl|null, newFullUrl, newVersion)`.

##### 4.2 Read the current URL or full history

```php
// Current active full URL (without recomputing)
$current = $product->getActiveFullUrl(); // e.g. "/shop/laptops/mbp-14"

// Full history (active + trashed by default)
$history = $product->urlHistory(); // Collection of Url models ordered by version asc
$activeOnly = $product->urlHistory(withTrashed: false);
```

##### 4.3 Resolving owners and redirects

```php
// Who currently owns a given active full URL?
$model = \JobMetric\Url\Models\Url::resolveActiveByFullUrl('/shop/laptops/mbp-14');

// If a URL is old (trashed), where should we redirect?
$target = \JobMetric\Url\Models\Url::resolveRedirectTarget('/shop/old-path'); 
// returns a current active URL string or null
```

##### Example redirect middleware (simplified):

```php
use Closure;
use JobMetric\Url\Models\Url;

class CanonicalRedirectMiddleware
{
    public function handle($request, Closure $next)
    {
        $path = '/'.ltrim($request->getPathInfo(), '/');

        // If requested path is obsolete, redirect to its canonical target
        if ($target = Url::resolveRedirectTarget($path)) {
            return redirect($target, 301);
        }

        return $next($request);
    }
}
```

#### 5) Cascading URL updates to descendants

If a parent’s path segment changes (e.g., a Category slug), you may need to refresh child URLs (e.g., Products). The trait supports this via an optional method on your model:

```php
// On the PARENT model
public function getUrlDescendants(): iterable
{
    // Return children whose URLs depend on this model
    return $this->products; // iterable of Models implementing UrlContract
}
```

When the parent’s slug changes, `HasUrl` will:

- Re-compute each child’s `getFullUrl()`,
- Version and insert the new active URL if changed,
- Throw `UrlConflictException` if a child’s new full URL conflicts with another model.

**Temporarily disable cascade:**

```php
$category->withoutUrlCascade(function () use ($category) {
    // Slug change without touching descendants
    $category->dispatchSlug('new-category-slug');
});
```

#### 6) Soft delete, restore, and force delete

- **Soft delete the parent model**
    - The single `slugs` row is soft-deleted.
    - All active `urls` rows are soft-deleted.

      (No model will claim the path anymore.)

- **Restore the parent model**
    - Before restoring, it checks slug conflicts (same type & collection).
    - On restored, it restores the `slugs` row and re-syncs the URL.

      If the full URL is already taken by another active record, it throws `UrlConflictException`.

- **Force delete the parent model**
    - Permanently removes its slug and all URL history.

**Examples**

```php
// Soft delete
$product->delete();    

// Restore (may throw SlugConflictException or UrlConflictException)
$product->restore();   

// Permanently delete
$product->forceDelete();
```

#### 7) Rebuilding URLs in bulk

Useful after changing your `getFullUrl()` logic or migrating data.

```php
use JobMetric\Url\Contracts\UrlContract;

Product::rebuildAllUrls(
    // Optional query hook to narrow the set
    function (\Illuminate\Database\Eloquent\Builder $q) {
        $q->where('status', 'published');
    },
    chunk: 1000
);
```

- Processes in chunks.
- Calls the same versioning logic as normal saves.
- Does not trigger your model’s `saved()` hooks or cascades (it directly re-syncs).

#### 8) Exceptions you should know

- `ModelUrlContractNotFoundException`

  Your model must implement `UrlContract`. The trait checks this at boot.

- `SlugConflictException`

  Another model of the same type already uses this slug (in the same collection).
  Handle this when calling `dispatchSlug()` or when restoring.

- `UrlConflictException`

  Another active model already owns the computed full URL.
  Can be thrown during saves, cascades, rebuilds, or restore.

**Example handling**

```php
try {
    $product->dispatchSlug('macbook-pro-14', 'products');
} catch (\JobMetric\Url\Exceptions\SlugConflictException $e) {
    // Ask user to pick a different slug
}
```

#### 9) API Reference (trait helpers)

> Methods below are provided by `HasUrl` unless otherwise noted.

**Slug methods**

```php
$product->dispatchSlug(?string $slug, ?string $collection = null): array;
// Upserts slug and syncs URL (returns ['ok' => bool, 'data' => SlugResource?])

$product->forgetSlug(?string $collection = null): array;
// Soft-deletes the slug row (if collection matches when provided)

$product->slug(): array;
// Envelope with SlugResource (default collection)

$product->slugByCollection(?string $collection = null, bool $mode = false): array|string|null;
// Envelope with SlugResource OR just slug string when $mode=true

$product->slug;                 // string|null (accessor)
$product->slug_resource;        // SlugResource|null (accessor)
$product->slug_collection;      // string|null (accessor)
```

**URL methods**

```php
$product->getActiveFullUrl(): ?string;
// Returns current active full URL without recomputing

$product->urlHistory(bool $withTrashed = true): \Illuminate\Support\Collection;
// Returns Url[] ordered by version asc

// Static utilities (on Url model via trait-provided statics)
\JobMetric\Url\Models\Url::resolveActiveByFullUrl(string $fullUrl): ?\Illuminate\Database\Eloquent\Model;
\JobMetric\Url\Models\Url::resolveRedirectTarget(string $fullUrl): ?string;
```

**Finders**

```php
Product::findBySlug(string $slug): ?Product;
Product::findBySlugOrFail(string $slug): ?Product; // throws SlugNotFoundException

Product::findBySlugAndCollection(string $slug, ?string $collection = null): ?Product;
Product::findBySlugAndCollectionOrFail(string $slug, ?string $collection = null): ?Product;
```

**Bulk operations**

```php
Product::rebuildAllUrls(?callable $queryHook = null, int $chunk = 500): void;
```

**Cascade control**

```php
$product->withoutUrlCascade(callable $fn): mixed;
// Temporarily disable descendant refresh inside the callback
```

#### 10) Real-World Examples

**10.1 Category → Product path dependency with cascade**

```php
class Category extends Model implements UrlContract
{
    use HasUrl;

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getFullUrl(): string
    {
        return '/shop/'.$this->slug;
    }

    public function getUrlDescendants(): iterable
    {
        return $this->products;
    }
}

class Product extends Model implements UrlContract
{
    use HasUrl;

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getFullUrl(): string
    {
        return '/shop/'.($this->category->slug ?? 'uncategorized').'/'.$this->slug;
    }
}

// Change category slug → all children get new versioned URLs
$category->dispatchSlug('laptops');
```

##### 10.2 Changing only the collection

```php
// Same slug, move to a new collection
$product->dispatchSlug('mbp-14', 'featured-products');
// Active URL remains, but the Url row’s collection field updates
```

##### 10.3 Handling a user-entered duplicate slug

```php
try {
    $product->dispatchSlug(request('slug')); // no collection
} catch (\JobMetric\Url\Exceptions\SlugConflictException $e) {
    return back()->withErrors([
        'slug' => 'This slug is already taken for this type.',
    ]);
}
```

**10.4 301 redirects from old URLs**

Combine `resolveRedirectTarget()` with a middleware (see §4.3). This protects SEO when URLs change over time.

#### 11) Recommended database indexes

> These are already baked into the package’s migrations. If you maintain your own schema, consider the following:

- `slugs` table
    - Unique composite: `(slugable_type, slugable_id, deleted_at)`
    - Optional unique composite for multi-collection setups: `(slugable_type, collection, slug, deleted_at)`

- `urls` table
    - Unique composite: `(urlable_type, urlable_id, version)`
    - Index on `full_url` with a filter on `deleted_at` can speed up conflict checks.

#### 12) Events

- `UrlChanged`
    - Fired after a new active URL row is created.
    - Signature: `new UrlChanged(Model&UrlContract $model, ?string $old, string $new, int $version)`

Use it to update caches, ping search engines, or trigger webhooks.

#### 13) Testing tips

- When asserting URL changes, check both:
    - The active URL row (`deleted_at NULL`, highest `version`).
    - The soft-deleted previous row for redirection logic.
- If you test cascades, ensure child models also implement `UrlContract`.

---

## Fallback Route (Smart URL Resolver)

This package can register a single Laravel fallback route that resolves any unmatched path using the versioned `urls` table:

- If there’s an **active** URL row → it fires an `UrlMatched` event so your app can decide what to return (product page, category page, CMS page, JSON, etc.).
- If there’s **only a legacy (soft-deleted)** match → it issues a **301 redirect** to the current canonical URL (SEO-friendly).
- Otherwise → returns a translated **404**.

**Enabling / Disabling**
The fallback is on **by default**. Control it in `config/url.php`:

```php
return [
    // Register the fallback router that pipes all unknown paths into FullUrlController
    'register_fallback' => true,

    // Middleware stack for the fallback route (defaults to 'web')
    'fallback_middleware' => ['web'],

    // ... other config
];
```

> The provider wires it for you:

```php
Route::middleware(config('url.fallback_middleware', ['web']))
->group(function () {
    Route::fallback(\JobMetric\Url\Http\Controllers\FullUrlController::class)
        ->name('JobMetric.url.fallback');
});
```

**How it resolves paths**

For a request like `GET /shop/laptops/mbp-14?color=silver`, the controller builds these **candidates** and looks them up (most recent first):

- `shop/laptops/mbp-14`
- `shop/laptops/mbp-14/`
- `/shop/laptops/mbp-14`
- `/shop/laptops/mbp-14/`
- `/` (root special-case for empty paths)

Then it:

1. Tries to find an **active** URL (`deleted_at NULL`, latest `version`).
2. If not found, checks **legacy** URLs (soft-deleted) and redirects (301) to the canonical URL of the same model (preserving query string).
3. If still nothing, returns **404** (translated: `trans('url::base.exceptions.not_found')`).

#### The `UrlMatched` Event

When an **active** `Url` row is found, the controller emits:

```php
new UrlMatched(Request $request, Url $url)
```

Useful properties:

- `$event->request` — the incoming `Illuminate\Http\Request`
- `$event->url` — the matched `Url` row (active)
- `$event->urlable` — the polymorphic **model instance** (e.g., Product, Category)
- `$event->collection` — optional URL collection string
- `$event->response` — initially `null`. **Your listener must set it** via `$event->respond($response)` to short-circuit and return the response.

> If no listener sets a response, the controller returns **404**.

#### Writing Listeners (Many Ways)

You can wire listeners in the **EventServiceProvider**, or use `Event::listen` at boot time, or register a dedicated **invokable** class. Below are several patterns with realistic content.

##### 1) Quick inline listener (closure) — Product page

`app/Providers/EventServiceProvider.php`

```php
use Illuminate\Support\Facades\Event;
use JobMetric\Url\Events\UrlMatched;
use App\Http\Controllers\ProductController;

public function boot(): void
{
    parent::boot();

    Event::listen(UrlMatched::class, function (UrlMatched $event) {
        // Route only Product models here
        if ($event->urlable instanceof \App\Models\Product) {
            // Delegate to your controller action
            $response = app(ProductController::class)->show($event->urlable);

            $event->respond($response);
        }
    });
}
```

`app/Http/Controllers/ProductController.php`

```php
namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        // Render your product page (Blade, Inertia, etc.)
        return view('shop.product', [
            'product' => $product,
            'canonical' => $product->getActiveFullUrl(),
        ]);
    }
}
```

##### 2) Another closure — Category page with pagination

```php
use Illuminate\Support\Facades\Event;
use JobMetric\Url\Events\UrlMatched;
use App\Http\Controllers\CategoryController;

Event::listen(UrlMatched::class, function (UrlMatched $event) {
    if ($event->urlable instanceof \App\Models\Category) {
        $page = (int) $event->request->query('page', 1);
        $response = app(CategoryController::class)->show($event->urlable, $page);

        $event->respond($response);
    }
});
```

`app/Http/Controllers/CategoryController.php`

```php
namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function show(Category $category, int $page = 1)
    {
        $products = $category->products()->paginate(24, ['*'], 'page', $page);

        return view('shop.category', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
```

##### 3) Invokable listener class — clean separation

`app/Listeners/HandleMatchedUrl.php`

```php
namespace App\Listeners;

use JobMetric\Url\Events\UrlMatched;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;

class HandleMatchedUrl
{
    public function __invoke(UrlMatched $event): void
    {
        $model = $event->urlable;

        if ($model instanceof \App\Models\Product) {
            $event->respond(app(ProductController::class)->show($model));
            return;
        }

        if ($model instanceof \App\Models\Category) {
            $event->respond(app(CategoryController::class)->show($model, (int) $event->request->query('page', 1)));
            return;
        }

        // Fallback: JSON for unknown urlables (optional)
        $event->respond(response()->json([
            'type' => class_basename($model),
            'id'   => $model->getKey(),
            'url'  => $event->url->full_url,
        ]));
    }
}
```

`app/Providers/EventServiceProvider.php`

```php
protected $listen = [
    \JobMetric\Url\Events\UrlMatched::class => [
        \App\Listeners\HandleMatchedUrl::class,
    ],
];
```

##### 4) Listener that does a custom redirect

Use this when you want to override the canonical route entirely.

```php
use Illuminate\Support\Facades\Event;
use JobMetric\Url\Events\UrlMatched;

Event::listen(UrlMatched::class, function (UrlMatched $event) {
    $model = $event->urlable;

    if ($model instanceof \App\Models\Product && $model->is_archived) {
        // Send archived products to a landing page
        $event->respond(redirect()->route('shop.archive'));
    }
});
```

##### 5) Listener returning an API response (JSON)

```php
Event::listen(UrlMatched::class, function (UrlMatched $event) {
    if ($event->request->wantsJson()) {
        $event->respond(response()->json([
            'url'       => $event->url->full_url,
            'collection'=> $event->collection,
            'type'      => class_basename($event->urlable),
            'data'      => $event->urlable->toArray(),
        ]));
    }
});
```

##### 6) Using route model binding after match (optional pattern)

You can also “bridge” into a named route:

```php
Event::listen(UrlMatched::class, function (UrlMatched $event) {
    $model = $event->urlable;

    if ($model instanceof \App\Models\Product) {
        $event->respond(
            redirect()->route('products.show', $model) // e.g., /products/{product}
        );
    }
});
```

##### Security, Middleware & Guards

You can add auth, localization, throttling, etc., by stacking middleware in `config/url.php`:

```php
'fallback_middleware' => ['web', 'localize', 'cache.headers:public;max_age=120'],
```

If a listener must be protected:

```php
Event::listen(UrlMatched::class, function (UrlMatched $event) {
    if ($event->urlable instanceof \App\Models\AdminPage) {
        if (!$event->request->user()?->can('view-admin-pages')) {
            $event->respond(response('Forbidden', 403));
            return;
        }

        $event->respond(view('admin.page', ['page' => $event->urlable]));
    }
});
```

##### Redirects from Legacy URLs (Built-in)

When a previously active URL becomes obsolete (soft-deleted), the fallback will automatically **301** to the model’s current canonical URL. Query strings are preserved:

- Request: `/old/path?ref=fb`
- Redirect: `301 → /new/path?ref=fb`

You don’t need to configure anything for this behavior; it’s baked into the controller.

##### Testing Recipes

##### 1) Exactly one event dispatched per request

```php
public function test_one_event_dispatched_per_request(): void
{
    \Illuminate\Support\Facades\Event::spy();

    // Provide a listener that returns a response, so the fallback doesn't 404
    \Illuminate\Support\Facades\Event::listen(\JobMetric\Url\Events\UrlMatched::class, function ($event) {
        $event->respond(response('OK', 200));
    });

    // Prepare a model that owns '/a'
    \JobMetric\Url\Tests\Stubs\Models\Category::factory()->setUrl('a')->create();

    $this->get('/a')->assertOk()->assertSee('OK');

    \Illuminate\Support\Facades\Event::assertDispatched(\JobMetric\Url\Events\UrlMatched::class, 1);
}
```

> If you see a `404` in the test, it usually means no listener set a response. Make sure your listener calls `$event->respond(...)`.

##### 2) Legacy redirect

```php
public function test_legacy_redirect_to_canonical(): void
{
    // Create a Product with initial slug, then change it to create a legacy URL
    $product = \App\Models\Product::factory()->create();
    $product->dispatchSlug('old-path');
    $product->dispatchSlug('new-path');

    $this->get('/old-path')->assertRedirect('/new-path');
}
```

##### 3) JSON response when `Accept: application/json`

```php
public function test_json_response_via_listener(): void
{
    \Illuminate\Support\Facades\Event::listen(\JobMetric\Url\Events\UrlMatched::class, function ($event) {
        if ($event->request->wantsJson()) {
            $event->respond(response()->json(['ok' => true, 'id' => $event->urlable->getKey()]));
        }
    });

    $model = \App\Models\Product::factory()->create();
    $model->dispatchSlug('api-item');

    $this->getJson('/api-item')
        ->assertOk()
        ->assertJson(['ok' => true]);
}
```

##### Troubleshooting

- **I get 404 on a known URL**
    - Ensure a listener **sets a response** via `$event->respond(...)`.
    - Confirm the URL row is **active** (not soft-deleted) and the model is present.
    - Check that `register_fallback` is `true` and the middleware group includes `web` (or your session/localization needs).

- **Infinite redirects**
    - Don’t redirect the same matched path to itself.
    - If you redirect into another path that also resolves to the same model, consider returning the view instead of chaining redirects.

- **Wrong page returned**
    - Check your “router” logic in the listener (e.g., `instanceof` checks).
    - Verify your model implements `UrlContract` and that `getFullUrl()` computes the intended canonical path.

##### Example: Full Setup Summary

1. Models implement `UrlContract` and use `HasUrl`.

```php
class Product extends Model implements \JobMetric\Url\Contracts\UrlContract
{
    use \JobMetric\Url\HasUrl;

    public function category() { return $this->belongsTo(Category::class); }

    public function getFullUrl(): string
    {
        $cat = $this->category?->slug ?? 'uncategorized';
        return "/shop/{$cat}/{$this->slug}";
    }
}
```

2. **Assign slugs** (versioned URL is created automatically).

```php
$product->dispatchSlug('mbp-14', 'products');
```

3. **Register listeners** to render pages.

```php
Event::listen(\JobMetric\Url\Events\UrlMatched::class, function ($event) {
    $model = $event->urlable;

    if ($model instanceof \App\Models\Product) {
        $event->respond(view('shop.product', ['product' => $model]));
        return;
    }

    if ($model instanceof \App\Models\Category) {
        $event->respond(view('shop.category', [
            'category' => $model,
            'products' => $model->products()->paginate(24),
        ]));
        return;
    }
});
```

4. **Enjoy free 301 redirects** for old paths (no extra code).

With this fallback + event pattern, you get **one URL entry point** that can render **anything** (products, categories, blogs, CMS pages) based on the database — while preserving SEO via **automatic legacy redirects**, and keeping your controllers and routes tidy.

---

## Validation: `SlugExistRule`

`SlugExistRule` validates that a **slug is unique** for a given model class and optional **collection**, ignoring soft-deleted rows and optionally excluding the **current record** (useful for update forms).

> Despite the name, it enforces `uniqueness` (it fails if a matching active slug already exists).
It also `normalizes` the incoming value exactly like the trait does: `Str::slug(trim($value))` and **limits it to 100 chars** before checking.

### Constructor

```php
new \JobMetric\Url\Rules\SlugExistRule(
    string   $className,            // Eloquent model class that uses HasUrl
    ?string  $collection = null,    // Optional collection; '' is treated as null
    ?int     $objectId   = null     // Current model ID to exclude (on update)
)
```

- **$className**: your Eloquent model FQCN (e.g., `App\Models\Product::class`).
- **$collection**: pass `null` for the default collection; pass a non-empty string to scope by collection.
- **$objectId**: exclude the current record when updating so the user can keep the same slug.

The rule queries the `slugs` table with:

- `slugable_type = $className`
- `collection = $collection` (or `NULL` if omitted)
- `deleted_at IS NULL` (only active rows)
- `slug = normalized($value)`
- `slugable_id != $objectId` (when provided)

If a row exists, validation fails with `trans('url::base.rule.exist')`.

##### Why use this rule?

- **Same normalization** as `HasUrl` → your validation view matches what will be stored.
- **Active-only** uniqueness → allows reusing slugs from soft-deleted records.
- **Update-safe** → exclude the current record by ID.
- **Prevents late exceptions** → catch conflicts before calling `dispatchSlug()`.

#### Common Recipes

##### 1) Create request: simple uniqueness in a fixed collection

```php
use Illuminate\Foundation\Http\FormRequest;
use JobMetric\Url\Rules\SlugExistRule;
use App\Models\Product;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'slug'  => [
                'required', 'string', 'max:100',
                new SlugExistRule(Product::class, 'products'),
            ],
            // optional: send a collection explicitly
            // 'slug_collection' => ['nullable', 'string', 'max:50'],
        ];
    }
}
```

> Tip: Set `max:100` to align with internal normalization (`Str::limit(..., 100)`).

##### 2) Update request: exclude current record

```php
use Illuminate\Foundation\Http\FormRequest;
use JobMetric\Url\Rules\SlugExistRule;
use App\Models\Product;

class UpdateProductRequest extends FormRequest
{
    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? null; // route-model binding

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:100',
                new SlugExistRule(Product::class, 'products', $productId),
            ],
        ];
    }
}
```

If the slug did not change, the rule allows it (because it excludes `$productId`).

##### 3) Dynamic collection from request (or model type)

```php
use JobMetric\Url\Rules\SlugExistRule;
use App\Models\Category;

public function rules(): array
{
    $collection = $this->input('slug_collection'); // '' will be treated as null by the rule

    return [
        'title'           => ['required', 'string', 'max:255'],
        'slug'            => ['required', 'string', 'max:100', new SlugExistRule(Category::class, $collection)],
        'slug_collection' => ['nullable', 'string', 'max:50'],
    ];
}
```

If you omit the collection entirely when you later call `dispatchSlug()`, the trait will fall back to your model’s `getSlugCollectionDefault()` or its `type` attribute (see the HasUrl docs).

##### 4) Programmatic validation (no FormRequest)

```php
use Illuminate\Support\Facades\Validator;
use JobMetric\Url\Rules\SlugExistRule;
use App\Models\Product;

$data = ['slug' => 'MacBook Pro 14'];
$rule = new SlugExistRule(Product::class, 'products');

$validator = Validator::make($data, [
    'slug' => ['required', 'string', 'max:100', $rule],
]);

$validator->validate(); // throws if collision exists
```

##### 5) Nested payloads or admin panels

```php
return [
    'product' => ['required', 'array'],
    'product.slug' => [
        'required', 'string', 'max:100',
        new SlugExistRule(\App\Models\Product::class, $this->input('product.slug_collection')),
    ],
];
```

#### Error Messages

By default, failures use `trans('url::base.rule.exist')`. You can override per-field:

```php
public function messages(): array
{
    return [
        'slug.required' => 'Please enter a slug.',
        // Override the package’s message for this field:
        'slug.*'        => 'This slug is already in use for this type/collection.',
    ];
}
```

Or customize the translation key in `resources/lang/{locale}/url/base.php`:

```php
return [
    'rule' => [
        'exist' => 'This slug is already taken.',
    ],
];
```

#### End-to-End Example

**Controller** (simplified):

```php
public function store(StoreProductRequest $request)
{
    $product = \App\Models\Product::create([
        'name' => $request->input('name'),
    ]);

    // Persist slug with an optional collection
    $product->dispatchSlug(
        $request->input('slug'),
        $request->input('slug_collection') // may be null
    );

    return redirect()->to($product->getActiveFullUrl());
}
```

- The request ensures **pre-flight uniqueness** with `SlugExistRule`.
- `dispatchSlug()` will upsert the `slugs` row and sync the versioned URL.

#### Testing the Rule

##### 1) It fails when another active slug exists

```php
use JobMetric\Url\Rules\SlugExistRule;

public function test_slug_rule_blocks_duplicates(): void
{
    $p1 = \App\Models\Product::factory()->create();
    $p1->dispatchSlug('existing', 'products');

    $rule = new SlugExistRule(\App\Models\Product::class, 'products');

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    validator(['slug' => 'existing'], [
        'slug' => ['required', 'string', 'max:100', $rule],
    ])->validate();
}
```

##### 2) It allows reusing a soft-deleted slug

```php
public function test_slug_rule_ignores_soft_deleted(): void
{
    $p = \App\Models\Product::factory()->create();
    $p->dispatchSlug('recyclable', 'products');
    $p->delete(); // soft-delete => slug row soft-deleted

    $rule = new SlugExistRule(\App\Models\Product::class, 'products');

    $this->assertTrue(
        validator(['slug' => 'recyclable'], [
            'slug' => ['required', 'string', 'max:100', $rule],
        ])->passes()
    );
}
```

##### 3) It allows keeping the same slug on update

```php
public function test_slug_rule_excludes_current_id_on_update(): void
{
    $p = \App\Models\Product::factory()->create();
    $p->dispatchSlug('keep-me', 'products');

    $rule = new SlugExistRule(\App\Models\Product::class, 'products', $p->id);

    $this->assertTrue(
        validator(['slug' => 'keep-me'], [
            'slug' => ['required', 'string', 'max:100', $rule],
        ])->passes()
    );
}
```

#### Pitfalls & Tips

- **Do not rely on** `unique`: **database rules for slugs:** they won’t match this package’s normalization and soft-delete semantics.
- **Match the 100-char limit** in your form rules. The rule internally truncates to 100; adding `max:100` gives clear UX.
- **Empty values are ignored by the rule** (let `required|string` handle presence).
- **Collection `''` is treated as `null`** by the rule for convenience.
- **This rule validates pre-flight; conflicts can still be thrown later** by `dispatchSlug()` if something changes between validation and save (rare, but possible under race conditions). Handle exceptions like `SlugConflictException` defensively in your save flow if needed.

With `SlugExistRule` in place, your forms catch slug collisions **before** calling `dispatchSlug()`, keeping user feedback fast and precise while staying perfectly aligned with how the package stores and normalizes slugs.

---

## Contributing

Thank you for considering contributing to the Laravel Url! The contribution guide can be found in the [CONTRIBUTING.md](https://github.com/jobmetric/laravel-url/blob/master/CONTRIBUTING.md).

--- 
## License

The MIT License (MIT). Please see [License File](https://github.com/jobmetric/laravel-url/blob/master/LICENCE.md) for more information.
