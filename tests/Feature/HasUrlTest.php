<?php

namespace JobMetric\Url\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use JobMetric\Url\Events\UrlChanged;
use JobMetric\Url\Exceptions\SlugConflictException;
use JobMetric\Url\Exceptions\SlugNotFoundException;
use JobMetric\Url\Exceptions\UrlConflictException;
use JobMetric\Url\Models\Slug;
use JobMetric\Url\Models\Url;
use JobMetric\Url\Tests\Stubs\Models\Category;
use JobMetric\Url\Tests\Stubs\Models\Product;
use JobMetric\Url\Tests\TestCase as BaseTestCase;
use Throwable;

/**
 * Feature tests for the HasUrl trait:
 * - Builds nested full URLs (category tree + product).
 * - Cascades URL changes to descendants.
 * - Soft delete / restore / force delete flows for slug and URLs.
 * - Versioning rules for URLs and collection changes.
 * - Global active full_url uniqueness across types.
 * - Conflict checks on restore (slug and full_url).
 * - Rebuild helpers, redirect helpers, and event dispatching.
 */
class HasUrlTest extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * It builds correct full URLs for nested categories and product:
     *   root (a) -> child (a/b) -> product (a/b/p1)
     * And starts versioning from 1 for active URLs.
     */
    public function test_it_builds_full_url_for_nested_categories_and_product()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $rootUrl = $this->activeUrl($root);
        $this->assertSame('a', $rootUrl->full_url);
        $this->assertSame(1, $rootUrl->version);

        $childUrl = $this->activeUrl($child);
        $this->assertSame('a/b', $childUrl->full_url);
        $this->assertSame(1, $childUrl->version);

        $productUrl = $this->activeUrl($product);
        $this->assertSame('a/b/p1', $productUrl->full_url);
        $this->assertSame(1, $productUrl->version);
    }

    /**
     * Changing the parent category slug cascades to its child and the product:
     * - Old paths are soft-deleted and new paths are inserted with version++.
     * - Child and product should both reflect the new ancestor path.
     */
    public function test_changing_parent_category_slug_cascades_to_descendant_category_and_product()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $this->assertSame('a/b', $this->activeUrl($child)->full_url);
        $this->assertSame('a/b/p1', $this->activeUrl($product)->full_url);

        $root->slug = 'x';
        $root->save();

        $childActive = $this->activeUrl($child);
        $this->assertSame('x/b', $childActive->full_url);
        $this->assertSame(2, $childActive->version);
        $this->assertNotNull(
            Url::query()->ofUrlable($child->getMorphClass(), $child->getKey())->onlyTrashed()->where('full_url', 'a/b')->first()
        );

        $productActive = $this->activeUrl($product);
        $this->assertSame('x/b/p1', $productActive->full_url);
        $this->assertSame(2, $productActive->version);
        $this->assertNotNull(
            Url::query()->ofUrlable($product->getMorphClass(), $product->getKey())->onlyTrashed()->where('full_url', 'a/b/p1')->first()
        );
    }

    /**
     * Changing the child category slug cascades to its products:
     * - The child's path changes and product path updates accordingly (version++).
     */
    public function test_changing_child_category_slug_cascades_to_product()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $child->slug = 'c';
        $child->save();

        $childActive = $this->activeUrl($child);
        $this->assertSame('a/c', $childActive->full_url);
        $this->assertSame(2, $childActive->version);

        $productActive = $this->activeUrl($product);
        $this->assertSame('a/c/p1', $productActive->full_url);
        $this->assertSame(2, $productActive->version);
    }

    /**
     * Soft deleting a product soft-deletes its slug row and all URL rows.
     */
    public function test_soft_deleting_product_soft_deletes_slug_and_urls()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p2')->create();

        $this->assertDatabaseHas(config('url.tables.slug'), [
            'slugable_type' => $product->getMorphClass(),
            'slugable_id' => $product->getKey(),
            'slug' => 'p2',
            'deleted_at' => null,
        ]);

        $this->assertNotNull($this->activeUrl($product));

        $product->delete();

        $this->assertSoftDeleted(config('url.tables.slug'), [
            'slugable_type' => $product->getMorphClass(),
            'slugable_id' => $product->getKey(),
        ]);

        $this->assertSoftDeleted(config('url.tables.url'), [
            'urlable_type' => $product->getMorphClass(),
            'urlable_id' => $product->getKey(),
        ]);
    }

    /**
     * Restoring a soft-deleted product restores its slug and resyncs its active URL.
     */
    public function test_restoring_product_restores_slug_and_resyncs_active_url()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p3')->create();

        $product->delete();
        $product->restore();

        $this->assertDatabaseHas(config('url.tables.slug'), [
            'slugable_type' => $product->getMorphClass(),
            'slugable_id' => $product->getKey(),
            'slug' => 'p3',
            'deleted_at' => null,
        ]);

        $active = $this->activeUrl($product);
        $this->assertNotNull($active);
        $this->assertSame('a/b/p3', $active->full_url);
    }

    /**
     * Force deleting a category permanently removes its slug rows
     * and all URL versions (active + trashed).
     */
    public function test_force_deleting_category_removes_slug_and_all_url_versions_permanently()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $root->slug = 'x';
        $root->save();
        $child->slug = 'y';
        $child->save();

        $rootId = $root->getKey();
        $rootType = $root->getMorphClass();

        $root->forceDelete();

        $this->assertDatabaseMissing(config('url.tables.slug'), [
            'slugable_type' => $rootType,
            'slugable_id' => $rootId,
        ]);

        $this->assertSame(0, Slug::withTrashed()->ofSlugable($rootType, $rootId)->count());
        $this->assertSame(0, Url::withTrashed()->ofUrlable($rootType, $rootId)->count());
    }

    /**
     * dispatchSlug() updates the model's slug (and collection if provided),
     * then resyncs the full URL to reflect the new path.
     */
    public function test_dispatch_slug_updates_slug_and_full_url()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $env = $child->dispatchSlug('c', null);
        $this->assertTrue($env['ok'] ?? false);

        $active = $this->activeUrl($child);
        $this->assertSame('a/c', $active->full_url);
    }

    /**
     * Basic slug resolvers return the expected model instance for both
     * findBySlug() and findBySlugAndCollection().
     */
    public function test_find_by_slug_and_collection_resolves_models()
    {
        $root = Category::factory()->setUrl('findme')->create();

        $found = Category::findBySlug('findme');
        $this->assertNotNull($found);
        $this->assertSame($root->id, $found->id);

        $found2 = Category::findBySlugAndCollection('findme', null);
        $this->assertNotNull($found2);
        $this->assertSame($root->id, $found2->id);
    }

    /**
     * Changing only the collection (with the same slug) updates collection on
     * the active Url row without bumping the version and without changing full_url.
     */
    public function test_changing_only_collection_updates_active_url_collection_without_version_bump()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $activeBefore = $this->activeUrl($child);
        $this->assertSame(1, $activeBefore->version);
        $this->assertNull($activeBefore->collection);

        $env = $child->dispatchSlug('b', 'blog');
        $this->assertTrue($env['ok'] ?? false);

        $activeAfter = $this->activeUrl($child);
        $this->assertSame('a/b', $activeAfter->full_url);
        $this->assertSame(1, $activeAfter->version);
        $this->assertSame('blog', $activeAfter->collection);
    }

    /**
     * Restoring a soft-deleted product fails when another active record already
     * uses the same slug in the same type/collection (SlugConflictException).
     */
    public function test_restoring_product_fails_if_slug_taken_by_another_active_record()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $p1 = Product::factory()->setCategoryId($child->id)->setUrl('same-slug')->create();
        $p1->delete();

        $p2 = Product::factory()->setCategoryId($child->id)->setUrl('same-slug')->create();

        $this->expectException(SlugConflictException::class);
        $p1->restore();
    }

    /**
     * When toggling back to an old full_url, trashed duplicates for that path
     * are purged and only one active row must remain for that full_url.
     */
    public function test_toggling_back_to_an_old_full_url_purges_trashed_duplicates_for_that_path()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $child->slug = 'c';
        $child->save();
        $this->assertSame('a/c', $this->activeUrl($child)->full_url);
        $this->assertSame('a/c/p1', $this->activeUrl($product)->full_url);

        $child->slug = 'b';
        $child->save();
        $this->assertSame('a/b/p1', $this->activeUrl($product)->full_url);

        $trashedBack = Url::query()
            ->where('full_url', 'a/b/p1')
            ->onlyTrashed()
            ->count();

        $this->assertSame(0, $trashedBack);
        $this->assertSame(1, Url::where('full_url', 'a/b/p1')->whereNull('deleted_at')->count());
    }

    /**
     * Disabling cascade with withoutUrlCascade() prevents children from refreshing
     * automatically when an ancestor's slug changes.
     */
    public function test_without_url_cascade_prevents_children_from_refreshing_automatically()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $root->withoutUrlCascade(function () use ($root) {
            $root->slug = 'x';
            $root->save();
        });

        $this->assertSame('a/b', $this->activeUrl($child)->full_url);
        $this->assertSame('a/b/p1', $this->activeUrl($product)->full_url);
    }

    /**
     * rebuildAllUrls() recomputes full URLs for the target model class.
     * After disabling cascade and changing an ancestor, calling rebuildAllUrls()
     * brings children back in sync.
     */
    public function test_rebuild_all_urls_for_products_refreshes_children_after_parent_change()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $root->withoutUrlCascade(function () use ($root) {
            $root->slug = 'x';
            $root->save();
        });

        Product::rebuildAllUrls();

        $this->assertSame('x/b/p1', $this->activeUrl($product)->full_url);
    }

    /**
     * resolveActiveByFullUrl() returns the model that currently owns a path.
     * resolveRedirectTarget() returns the canonical path for a legacy (soft-deleted) URL.
     */
    public function test_helpers_resolve_active_and_redirect_paths_correctly()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $this->assertSame($product->id, Product::resolveActiveByFullUrl('a/b/p1')?->id);

        $child->slug = 'c';
        $child->save();

        $this->assertNull(Product::resolveActiveByFullUrl('a/b/p1'));
        $this->assertSame('a/c/p1', Product::resolveRedirectTarget('a/b/p1'));
    }

    /**
     * Changing only the collection via dispatchSlug() with the same slug
     * is a no-op for version bump, but updates the collection field.
     */
    public function test_changing_only_collection_does_not_bump_version_and_updates_collection()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $before = $this->activeUrl($child);
        $this->assertSame(1, $before->version);
        $this->assertNull($before->collection);

        $env = $child->dispatchSlug('b', 'blog');
        $this->assertTrue($env['ok'] ?? false);

        $after = $this->activeUrl($child);
        $this->assertSame('a/b', $after->full_url);
        $this->assertSame(1, $after->version);
        $this->assertSame('blog', $after->collection);
    }

    /**
     * Active full_url must be globally unique across types.
     * Category takes 'a'; Product tries to take 'a' too (no category => product full_url == slug).
     * This must fail with UrlConflictException.
     */
    public function test_active_full_url_must_be_globally_unique_across_types()
    {
        Category::factory()->setUrl('a')->create();

        $this->expectException(UrlConflictException::class);

        Product::factory()
            ->setCategoryId(null)
            ->setUrl('a')
            ->create();
    }

    /**
     * Restoring fails if another active record in the same type/collection
     * already uses the same slug (SlugConflictException).
     */
    public function test_restoring_fails_if_slug_is_taken_by_another_active_record()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $p1 = Product::factory()->setCategoryId($child->id)->setUrl('pX')->create();
        $p1->delete();

        Product::factory()->setCategoryId($child->id)->setUrl('pX')->create();

        $this->expectException(SlugConflictException::class);
        $p1->restore();
    }

    /**
     * rebuildAllUrls() supports an optional query hook to limit the rows that get resynced.
     * Here only one product is rebuilt and the other remains unchanged.
     *
     * @throws Throwable
     */
    public function test_rebuild_all_urls_supports_a_query_hook_to_filter_target_rows()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $p1 = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();
        $p2 = Product::factory()->setCategoryId($child->id)->setUrl('p2')->create();

        $root->withoutUrlCascade(function () use ($root) {
            $root->slug = 'x';
            $root->save();
        });

        Product::rebuildAllUrls(function ($q) use ($p1) {
            $q->whereKey($p1->id);
        });

        $this->assertSame('x/b/p1', $this->activeUrl($p1)->full_url);
        $this->assertSame('a/b/p2', $this->activeUrl($p2)->full_url);
    }

    /**
     * forgetSlug() soft-deletes the slug row but leaves the active URL record intact.
     */
    public function test_forget_slug_deletes_slug_row_but_keeps_active_url()
    {
        $root = Category::factory()->setUrl('a')->create();

        $this->assertNotNull($this->activeUrl($root));
        $this->assertDatabaseHas(config('url.tables.slug'), [
            'slugable_type' => $root->getMorphClass(),
            'slugable_id' => $root->getKey(),
        ]);

        $root->forgetSlug();

        $this->assertDatabaseMissing(config('url.tables.slug'), [
            'slugable_type' => $root->getMorphClass(),
            'slugable_id' => $root->getKey(),
            'deleted_at' => null,
        ]);

        $this->assertNotNull($this->activeUrl($root));
    }

    /**
     * dispatchSlug() with the same slug/collection is a no-op for versioning
     * and keeps the current active full_url unchanged.
     */
    public function test_dispatch_slug_with_same_values_is_noop_no_version_bump()
    {
        $root = Category::factory()->setUrl('a')->create();
        $before = $this->activeUrl($root);
        $this->assertSame(1, $before->version);

        $env = $root->dispatchSlug('a', null);
        $this->assertTrue($env['ok'] ?? false);

        $after = $this->activeUrl($root);
        $this->assertSame(1, $after->version);
        $this->assertSame('a', $after->full_url);
    }

    /**
     * UrlChanged event is fired on create and on URL change (version bump).
     * Asserts dispatch count and payload with a closure.
     */
    public function test_url_changed_event_is_fired_on_create_and_update_and_cascade()
    {
        Event::fake([UrlChanged::class]);

        $root = Category::factory()->setUrl('a')->create();
        Event::assertDispatched(UrlChanged::class, 1);

        $root->slug = 'x';
        $root->save();
        Event::assertDispatched(UrlChanged::class, 2);

        Event::assertDispatched(UrlChanged::class, function ($e) use ($root) {
            return $e->model->getKey() === $root->getKey() && $e->new === 'x';
        });
    }

    /**
     * findBySlugOrFail() throws SlugNotFoundException when no record matches.
     *
     * @throws Throwable
     */
    public function test_find_by_slug_or_fail_throws_when_missing()
    {
        $this->expectException(SlugNotFoundException::class);
        Category::findBySlugOrFail('not-exist');
    }

    /**
     * urlHistory() returns the versioned URL history (active + trashed),
     * ordered by version ascending.
     */
    public function test_url_history_returns_versions_in_order_including_trashed()
    {
        $root = Category::factory()->setUrl('a')->create();
        $root->slug = 'x';
        $root->save();
        $root->slug = 'y';
        $root->save();

        $history = $root->urlHistory();
        $this->assertCount(3, $history);
        $this->assertSame([1, 2, 3], $history->pluck('version')->all());
        $this->assertSame(['a', 'x', 'y'], $history->pluck('full_url')->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get the active (non-deleted) Url row for a given model.
     */
    private function activeUrl($model): ?Url
    {
        return Url::query()
            ->ofUrlable($model->getMorphClass(), $model->getKey())
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first();
    }
}
