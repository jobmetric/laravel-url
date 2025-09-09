<?php

namespace JobMetric\Url\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JobMetric\Url\Exceptions\SlugConflictException;
use JobMetric\Url\Models\Slug;
use JobMetric\Url\Models\Url;
use JobMetric\Url\Tests\Stubs\Models\Category;
use JobMetric\Url\Tests\Stubs\Models\Product;
use JobMetric\Url\Tests\TestCase as BaseTestCase;

class HasUrlTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_it_builds_full_url_for_nested_categories_and_product()
    {
        // root: a
        $root = Category::factory()->setUrl('a')->create();

        // child: a/b
        $child = Category::factory()
            ->setParentId($root->id)
            ->setUrl('b')
            ->create();

        // product under child: a/b/p1
        $product = Product::factory()
            ->setCategoryId($child->id)
            ->setUrl('p1')
            ->create();

        // root url
        $rootUrl = $this->activeUrl($root);
        $this->assertSame('a', $rootUrl->full_url);
        $this->assertSame(1, $rootUrl->version);

        // child url
        $childUrl = $this->activeUrl($child);
        $this->assertSame('a/b', $childUrl->full_url);
        $this->assertSame(1, $childUrl->version);

        // product url
        $productUrl = $this->activeUrl($product);
        $this->assertSame('a/b/p1', $productUrl->full_url);
        $this->assertSame(1, $productUrl->version);
    }

    public function test_changing_parent_category_slug_cascades_to_descendant_category_and_product()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        // sanity
        $this->assertSame('a/b', $this->activeUrl($child)->full_url);
        $this->assertSame('a/b/p1', $this->activeUrl($product)->full_url);

        // change root slug -> x (should cascade)
        $root->slug = 'x';
        $root->save();

        // child should be x/b (version++ from 1->2)
        $childActive = $this->activeUrl($child);
        $this->assertSame('x/b', $childActive->full_url);
        $this->assertSame(2, $childActive->version);
        $this->assertNotNull(
            Url::query()->ofUrlable($child->getMorphClass(), $child->getKey())->onlyTrashed()->where('full_url', 'a/b')->first()
        );

        // product should be x/b/p1 (version++ from 1->2)
        $productActive = $this->activeUrl($product);
        $this->assertSame('x/b/p1', $productActive->full_url);
        $this->assertSame(2, $productActive->version);
        $this->assertNotNull(
            Url::query()->ofUrlable($product->getMorphClass(), $product->getKey())->onlyTrashed()->where('full_url', 'a/b/p1')->first()
        );
    }

    public function test_changing_child_category_slug_cascades_to_product()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        // change child slug -> c (should cascade to product)
        $child->slug = 'c';
        $child->save();

        $childActive = $this->activeUrl($child);
        $this->assertSame('a/c', $childActive->full_url);
        $this->assertSame(2, $childActive->version);

        $productActive = $this->activeUrl($product);
        $this->assertSame('a/c/p1', $productActive->full_url);
        $this->assertSame(2, $productActive->version);
    }

    public function test_soft_deleting_product_soft_deletes_slug_and_urls()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p2')->create();

        $this->assertDatabaseHas(config('url.tables.slug'), [
            'slugable_type' => $product->getMorphClass(),
            'slugable_id'   => $product->getKey(),
            'slug'          => 'p2',
            'deleted_at'    => null,
        ]);

        $this->assertNotNull($this->activeUrl($product));

        $product->delete();

        $this->assertSoftDeleted(config('url.tables.slug'), [
            'slugable_type' => $product->getMorphClass(),
            'slugable_id'   => $product->getKey(),
        ]);

        $this->assertSoftDeleted(config('url.tables.url'), [
            'urlable_type' => $product->getMorphClass(),
            'urlable_id'   => $product->getKey(),
        ]);
    }

    public function test_restoring_product_restores_slug_and_resyncs_active_url()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p3')->create();

        $product->delete();
        $product->restore();

        // slug restored
        $this->assertDatabaseHas(config('url.tables.slug'), [
            'slugable_type' => $product->getMorphClass(),
            'slugable_id'   => $product->getKey(),
            'slug'          => 'p3',
            'deleted_at'    => null,
        ]);

        // active url present and correct
        $active = $this->activeUrl($product);
        $this->assertNotNull($active);
        $this->assertSame('a/b/p3', $active->full_url);
    }

    public function test_force_deleting_category_removes_slug_and_all_url_versions_permanently()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        // bump versions
        $root->slug = 'x';
        $root->save();
        $child->slug = 'y';
        $child->save();

        $rootId = $root->getKey();
        $rootType = $root->getMorphClass();

        $root->forceDelete();

        $this->assertDatabaseMissing(config('url.tables.slug'), [
            'slugable_type' => $rootType,
            'slugable_id'   => $rootId,
        ]);

        $this->assertSame(0, Slug::withTrashed()->ofSlugable($rootType, $rootId)->count());
        $this->assertSame(0, Url::withTrashed()->ofUrlable($rootType, $rootId)->count());
    }

    public function test_dispatch_slug_updates_slug_and_full_url()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        // change child via dispatchSlug
        $env = $child->dispatchSlug('c', null);
        $this->assertTrue($env['ok'] ?? false);

        $active = $this->activeUrl($child);
        $this->assertSame('a/c', $active->full_url);
    }

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

    public function test_changing_only_collection_updates_active_url_collection_without_version_bump()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $activeBefore = $this->activeUrl($child);
        $this->assertSame(1, $activeBefore->version);
        $this->assertNull($activeBefore->collection);

        // Same slug, new collection
        $env = $child->dispatchSlug('b', 'blog');
        $this->assertTrue($env['ok'] ?? false);

        $activeAfter = $this->activeUrl($child);
        $this->assertSame('a/b', $activeAfter->full_url);
        // No version bump expected
        $this->assertSame(1, $activeAfter->version);
        $this->assertSame('blog', $activeAfter->collection);
    }

    public function test_restoring_product_fails_if_slug_taken_by_another_active_record()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();

        $p1 = Product::factory()->setCategoryId($child->id)->setUrl('same-slug')->create();
        // Soft delete original
        $p1->delete();

        // Create another active product with the same slug (allowed because p1 is soft-deleted)
        $p2 = Product::factory()->setCategoryId($child->id)->setUrl('same-slug')->create();

        // Restoring p1 should fail with SlugConflictException
        $this->expectException(SlugConflictException::class);
        $p1->restore();
    }

    public function test_toggling_back_to_an_old_full_url_purges_trashed_duplicates_for_that_path()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        // a/b/p1 -> a/c/p1 (version++)
        $child->slug = 'c';
        $child->save();
        $this->assertSame('a/c', $this->activeUrl($child)->full_url);
        $this->assertSame('a/c/p1', $this->activeUrl($product)->full_url);

        // Now back to a/b/p1
        $child->slug = 'b';
        $child->save();
        $this->assertSame('a/b/p1', $this->activeUrl($product)->full_url);

        // Purge behavior: there should be no trashed rows with full_url 'a/b/p1'
        $trashedBack = Url::query()
            ->where('full_url', 'a/b/p1')
            ->onlyTrashed()
            ->count();

        $this->assertSame(0, $trashedBack);
    }

    public function test_without_url_cascade_prevents_children_from_refreshing_automatically()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        // Change root slug but disable cascade
        $root->withoutUrlCascade(function () use ($root) {
            $root->slug = 'x';
            $root->save();
        });

        // Child and product URLs should remain unchanged
        $this->assertSame('a/b', $this->activeUrl($child)->full_url);
        $this->assertSame('a/b/p1', $this->activeUrl($product)->full_url);
    }

    public function test_rebuild_all_urls_for_products_refreshes_children_after_parent_change()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        // Change root with cascade disabled
        $root->withoutUrlCascade(function () use ($root) {
            $root->slug = 'x';
            $root->save();
        });

        // Rebuild URLs for products
        Product::rebuildAllUrls();

        // Product should now reflect the new path
        $this->assertSame('x/b/p1', $this->activeUrl($product)->full_url);
    }

    public function test_helpers_resolve_active_and_redirect_paths_correctly()
    {
        $root = Category::factory()->setUrl('a')->create();
        $child = Category::factory()->setParentId($root->id)->setUrl('b')->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl('p1')->create();

        $this->assertSame($product->id, Product::resolveActiveByFullUrl('a/b/p1')?->id);

        // Change child to 'c' => old 'a/b/p1' becomes legacy
        $child->slug = 'c';
        $child->save();

        $this->assertNull(Product::resolveActiveByFullUrl('a/b/p1'));
        $this->assertSame('a/c/p1', Product::resolveRedirectTarget('a/b/p1'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get the active (non-deleted) Url row for a model.
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
