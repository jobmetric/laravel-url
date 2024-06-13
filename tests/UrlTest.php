<?php

namespace JobMetric\Url\Tests;

use App\Models\Product;
use JobMetric\Url\Exceptions\UrlNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\BaseDatabaseTestCase as BaseTestCase;
use Throwable;

class UrlTest extends BaseTestCase
{
    public function testStoreAndUpdate(): void
    {
        // store product
        /** @var Product $product */
        $product = Product::create([
            'status' => true,
        ]);

        $url = $product->dispatchUrl('product-1');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-1', $url['data']->url);
        $this->assertEquals(201, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);

        $url = $product->dispatchUrl('product-2');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-2', $url['data']->url);
        $this->assertEquals(200, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-2',
        ]);
    }

    public function testGet(): void
    {
        // store product
        /** @var Product $product */
        $product = Product::create([
            'status' => true,
        ]);

        $url = $product->dispatchUrl('product-1');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-1', $url['data']->url);
        $this->assertEquals(201, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);

        $url = $product->getUrl();
        $this->assertEquals('product-1', $url);

        $url = $product->url;
        $this->assertEquals('product-1', $url);

        $url_collection = $product->url_collection;
        $this->assertNull($url_collection);
    }

    public function testForget(): void
    {
        // store product
        /** @var Product $product */
        $product = Product::create([
            'status' => true,
        ]);

        $url = $product->dispatchUrl('product-1');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-1', $url['data']->url);
        $this->assertEquals(201, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);

        $url = $product->forgetUrl();

        $this->assertTrue($url['ok']);
        $this->assertEquals(trans('url::base.messages.deleted'), $url['message']);
        $this->assertEquals(200, $url['status']);
        $this->assertDatabaseMissing('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);
    }

    public function testFindByUrlAndCollection(): void
    {
        // store product
        /** @var Product $product */
        $product = Product::create([
            'status' => true,
        ]);

        $url = $product->dispatchUrl('product-1');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-1', $url['data']->url);
        $this->assertEquals(201, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);

        /** @var Product $product */
        $product = Product::findByUrlAndCollection('product-1');

        $this->assertNotNull($product);
        $this->assertEquals('product-1', $product->url);
        $this->assertInstanceOf(Product::class, $product);

        $product = Product::findByUrlAndCollection('product-2');

        $this->assertNull($product);
    }

    /**
     * @throws Throwable
     */
    public function testFindByUrlAndCollectionOrFail(): void
    {
        // store product
        /** @var Product $product */
        $product = Product::create([
            'status' => true,
        ]);

        $url = $product->dispatchUrl('product-1');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-1', $url['data']->url);
        $this->assertEquals(201, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);

        /** @var Product $product */
        $product = Product::findByUrlAndCollectionOrFail('product-1');

        $this->assertNotNull($product);
        $this->assertEquals('product-1', $product->url);
        $this->assertInstanceOf(Product::class, $product);

        try {
            Product::findByUrlAndCollectionOrFail('product-2');
        } catch (UrlNotFoundException $e) {
            $this->assertInstanceOf(UrlNotFoundException::class, $e);
        }
    }

    public function testFindByUrlAndCollectionOrFailWithNotFoundHttpException(): void
    {
        // store product
        /** @var Product $product */
        $product = Product::create([
            'status' => true,
        ]);

        $url = $product->dispatchUrl('product-1');

        $this->assertTrue($url['ok']);
        $this->assertEquals('product-1', $url['data']->url);
        $this->assertEquals(201, $url['status']);
        $this->assertDatabaseHas('urls', [
            'urlable_type' => Product::class,
            'urlable_id' => $product->getKey(),
            'url' => 'product-1',
        ]);

        /** @var Product $product */
        $product = Product::findByUrlAndCollectionOrFail('product-1');

        $this->assertNotNull($product);
        $this->assertEquals('product-1', $product->url);
        $this->assertInstanceOf(Product::class, $product);

        try {
            Product::findByUrlAndCollectionOrFail('product-2', 'collection');
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }
}
