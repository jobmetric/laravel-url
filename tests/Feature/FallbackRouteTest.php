<?php

namespace JobMetric\Url\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use JobMetric\Url\Events\UrlMatched;
use JobMetric\Url\Models\Url;
use JobMetric\Url\Tests\Stubs\Models\Category;
use JobMetric\Url\Tests\Stubs\Models\Product;
use JobMetric\Url\Tests\TestCase as BaseTestCase;
use Random\RandomException;

class FallbackRouteTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Avoid listener leaks between tests
        Event::forget(UrlMatched::class);
    }

    /**
     * Make a short random slug suffix to avoid cross-test collisions.
     *
     * @throws RandomException
     */
    private function r(string $base): string
    {
        return $base . '-' . bin2hex(random_bytes(2));
    }

    /**
     * Resolves an active URL and allows a listener to return a custom response.
     *
     * @throws RandomException
     */
    public function test_fallback_resolves_active_url_and_dispatches_listener_response(): void
    {
        $a = $this->r('a');
        $b = $this->r('b');
        $p1 = $this->r('p1');

        // a/b/p1
        $root = Category::factory()->setUrl($a)->create();
        $child = Category::factory()->setParentId($root->id)->setUrl($b)->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl($p1)->create();

        Event::listen(UrlMatched::class, function (UrlMatched $event) use ($product) {
            $this->assertSame($product->getKey(), $event->urlable->getKey());
            $event->respond(response('OK product', 200));
        });

        $this->get("/{$a}/{$b}/{$p1}")
            ->assertOk()
            ->assertSee('OK product');
    }

    /**
     * Legacy (soft-deleted) URL should 301-redirect to the current canonical URL.
     *
     * @throws RandomException
     */
    public function test_fallback_redirects_legacy_url_to_canonical_301(): void
    {
        $a = $this->r('a');
        $b = $this->r('b');
        $c = $this->r('c');
        $p1 = $this->r('p1');

        // Start at a/b/p1
        $root = Category::factory()->setUrl($a)->create();
        $child = Category::factory()->setParentId($root->id)->setUrl($b)->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl($p1)->create();

        // Change child's slug to "c" -> product moves to a/c/p1; a/b/p1 becomes legacy.
        $child->slug = $c;
        $child->save();

        $this->get("/{$a}/{$b}/{$p1}")
            ->assertStatus(301)
            ->assertRedirect("/{$a}/{$c}/{$p1}");

        $this->get("/{$a}/{$b}/{$p1}?x=1&y=2")
            ->assertStatus(301)
            ->assertRedirect("/{$a}/{$c}/{$p1}?x=1&y=2");
    }

    /**
     * Returns 404 for unknown paths that have no active or legacy records.
     *
     * @throws RandomException
     */
    public function test_fallback_returns_404_when_not_found(): void
    {
        $unknown = $this->r('does') . '/' . $this->r('not') . '/' . $this->r('exist');

        $this->get("/{$unknown}")->assertNotFound();
    }

    /**
     * 404 when an active Url exists but its urlable cannot be resolved.
     *
     * @throws RandomException
     */
    public function test_fallback_404_when_urlable_is_missing(): void
    {
        $ghost = $this->r('ghost');

        Url::query()->create([
            'urlable_type' => Category::class,
            'urlable_id' => 999999, // non-existent
            'full_url' => $ghost,
            'collection' => null,
            'version' => 1,
        ]);

        $this->get("/{$ghost}")->assertNotFound();
    }

    /**
     * 404 when no listener responds to UrlMatched.
     *
     * @throws RandomException
     */
    public function test_fallback_returns_404_when_no_listener_handles_the_request(): void
    {
        $a = $this->r('a');
        $b = $this->r('b');
        $p1 = $this->r('p1');

        $root = Category::factory()->setUrl($a)->create();
        $child = Category::factory()->setParentId($root->id)->setUrl($b)->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl($p1)->create();

        Event::listen(UrlMatched::class, function (UrlMatched $event) use ($product) {
            $this->assertSame($product->getKey(), $event->urlable->getKey());
            // no respond() -> controller must return 404
        });

        $this->get("/{$a}/{$b}/{$p1}")->assertNotFound();
    }

    /**
     * Trailing slash should be normalized; `/a/b/p1/` resolves same as `/a/b/p1`.
     *
     * @throws RandomException
     */
    public function test_trailing_slash_is_normalized_and_resolves(): void
    {
        $a = $this->r('a');
        $b = $this->r('b');
        $p1 = $this->r('p1');

        $root = Category::factory()->setUrl($a)->create();
        $child = Category::factory()->setParentId($root->id)->setUrl($b)->create();
        $product = Product::factory()->setCategoryId($child->id)->setUrl($p1)->create();

        Event::listen(UrlMatched::class, function (UrlMatched $event) {
            $event->respond(response('TRAILING', 200));
        });

        $this->get("/{$a}/{$b}/{$p1}/")->assertOk()->assertSee('TRAILING');
        $this->get("/{$a}/{$b}/{$p1}")->assertOk()->assertSee('TRAILING');
    }

    /**
     * Listener should receive the collection value present on the active Url row.
     *
     * @throws RandomException
     */
    public function test_listener_receives_collection_from_active_url(): void
    {
        $a = $this->r('a');

        // Build with collection already set (avoid re-dispatching same slug)
        $root = Category::factory()->setUrl($a, 'blog')->create();

        Event::listen(UrlMatched::class, function (UrlMatched $event) {
            $this->assertSame('blog', $event->collection);
            $event->respond(response('COLL', 200));
        });

        $this->get("/{$a}")->assertOk()->assertSee('COLL');
    }

    /**
     * Multiple listeners: last registered listener wins if both set a response.
     *
     * @throws RandomException
     */
    public function test_multiple_listeners_last_response_wins(): void
    {
        $a = $this->r('a');

        Category::factory()->setUrl($a)->create();

        Event::listen(UrlMatched::class, function (UrlMatched $event) {
            $event->respond(response('FIRST', 200));
        });

        Event::listen(UrlMatched::class, function (UrlMatched $event) {
            $event->respond(response('SECOND', 200));
        });

        $this->get("/{$a}")->assertOk()->assertSee('SECOND');
    }

    /**
     * Legacy present but no current canonical: should return 404 (no redirect target).
     *
     * @throws RandomException
     */
    public function test_legacy_without_current_canonical_returns_404(): void
    {
        $stale = $this->r('stale') . '/' . $this->r('path');

        $legacy = Url::query()->create([
            'urlable_type' => Category::class,
            'urlable_id' => 99999,
            'full_url' => $stale,
            'collection' => null,
            'version' => 2,
        ]);

        $legacy->delete();

        $this->get("/{$stale}")->assertNotFound();
    }

    /**
     * A listener can return a redirect response which the controller must pass through.
     * @throws RandomException
     */
    public function test_listener_can_return_redirect_response(): void
    {
        $a = $this->r('a');

        Category::factory()->setUrl($a)->create();

        Event::listen(UrlMatched::class, function (UrlMatched $event) {
            $event->respond(redirect('/go', 302));
        });

        $this->get("/{$a}")->assertStatus(302)->assertRedirect('/go');
    }

    /**
     * Exactly one UrlMatched event per request.
     *
     * @throws RandomException
     */
    public function test_one_event_dispatch_per_request(): void
    {
        $a = $this->r('a');

        $dispatches = 0;

        Event::listen(UrlMatched::class, function (UrlMatched $event) use (&$dispatches) {
            $dispatches++;
            $event->respond(response('X', 200));
        });

        Category::factory()->setUrl($a)->create();

        $this->get("/{$a}")->assertOk()->assertSee('X');

        $this->assertSame(1, $dispatches);
    }
}
