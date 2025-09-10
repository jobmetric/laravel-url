<?php

namespace JobMetric\Url\Tests\Feature\Rules;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use JobMetric\Url\Rules\SlugExistRule;
use JobMetric\Url\Tests\Stubs\Models\Category;
use JobMetric\Url\Tests\TestCase as BaseTestCase;

/**
 * Feature tests for SlugExistRule:
 * - Allows null/empty inputs (delegated to other rules).
 * - Enforces per-type + per-collection uniqueness among active (non-deleted) slugs.
 * - Ignores soft-deleted slug records.
 * - Respects collection scoping (conflict only when collection matches).
 * - Excludes the current object's ID for update flows.
 * - Normalizes input like HasUrl::normalizeSlugPair (slugify + trim + 100-char limit).
 */
class SlugExistRuleTest extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * It allows null or empty string values to pass (letting other rules handle required/string).
     */
    public function test_allows_null_or_empty_values(): void
    {
        $this->assertTrue($this->passes(null));
        $this->assertTrue($this->passes(''));
    }

    /**
     * It passes when the slug is unique within the given type and collection.
     */
    public function test_passes_when_unique_within_type_and_collection(): void
    {
        Category::factory()->setUrl('existing')->create();

        $this->assertTrue($this->passes('new-slug'));                        // default collection (null)
        $this->assertTrue($this->passes('new-slug', 'blog'));       // custom collection
    }

    /**
     * It fails when a duplicate active slug exists in the same type and collection (null).
     */
    public function test_fails_when_duplicate_active_slug_in_same_type_and_collection(): void
    {
        Category::factory()->setUrl('dup')->create();

        $this->assertFalse($this->passes('dup')); // same type, same (null) collection
        $this->assertSame(trans('url::base.rule.exist'), $this->firstError());
    }

    /**
     * It ignores soft-deleted slug records while checking uniqueness.
     */
    public function test_ignores_soft_deleted_slug_records(): void
    {
        $cat = Category::factory()->setUrl('gone')->create();
        $cat->delete(); // slug row becomes soft-deleted

        $this->assertTrue($this->passes('gone')); // allowed because prior record is trashed
    }

    /**
     * It scopes conflicts by collection: same slug + same collection => fail, different collection => pass.
     */
    public function test_scopes_by_collection(): void
    {
        Category::factory()->setUrl('same', 'blog')->create();

        $this->assertFalse($this->passes('same', 'blog')); // conflict
        $this->assertTrue($this->passes('same', null));    // different collection
        $this->assertTrue($this->passes('same', 'shop'));  // different collection
    }

    /**
     * It excludes the current object's ID so updating with the same slug passes.
     */
    public function test_excludes_current_object_id_for_updates(): void
    {
        $cat = Category::factory()->setUrl('self-slug')->create();

        // Simulate updating the same record: exclude its ID from uniqueness check
        $this->assertTrue($this->passes('self-slug', null, $cat->id));
    }

    /**
     * It normalizes input via slugify and trims, so equivalent forms trigger a conflict.
     */
    public function test_normalizes_input_like_trait_slugify_and_trim(): void
    {
        // Stored as "my-slug"
        Category::factory()->setUrl('my-slug')->create();

        // Equivalent raw variants normalize to "my-slug" => should fail
        $this->assertFalse($this->passes('  My Slug  '));
        $this->assertFalse($this->passes('My Slug!!!'));
        $this->assertSame(trans('url::base.rule.exist'), $this->firstError());
    }

    /**
     * It limits to 100 chars when comparing: different raw values that collapse to the same
     * first 100 chars should conflict.
     */
    public function test_limits_slug_to_100_chars_when_comparing(): void
    {
        $oneHundredAs = str_repeat('a', 100);
        $longAs = str_repeat('a', 120); // both normalize/limit to 100 'a'

        Category::factory()->setUrl($oneHundredAs)->create();

        $this->assertFalse($this->passes($longAs));
        $this->assertSame(trans('url::base.rule.exist'), $this->firstError());
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Run validation against the SlugExistRule for Category::class.
     *
     * @param string|null $value The candidate slug value.
     * @param string|null $collection Optional collection scope.
     * @param int|null $objectId Optional model ID to exclude (update scenario).
     */
    private function passes(?string $value, ?string $collection = null, ?int $objectId = null): bool
    {
        $validator = Validator::make(
            ['slug' => $value],
            ['slug' => [new SlugExistRule(Category::class, $collection, $objectId)]]
        );

        // Stash for error assertions
        $this->lastValidator = $validator;

        return $validator->passes();
    }

    /**
     * Returns the first validation error message for "slug", if any.
     */
    private function firstError(): ?string
    {
        return $this->lastValidator?->errors()->first('slug');
    }

    /**
     * Holds the last validator run to allow error message inspection.
     *
     * @var \Illuminate\Contracts\Validation\Validator|null
     */
    private ?\Illuminate\Contracts\Validation\Validator $lastValidator = null;
}
