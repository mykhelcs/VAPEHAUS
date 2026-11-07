<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Infrastructure\Cache;

use Vapehaus\Infrastructure\Cache\FileCache;
use Vapehaus\Infrastructure\Cache\InMemoryCache;
use Vapehaus\Tests\TestCase;

final class CacheStoreTest extends TestCase
{
    private string $tempCacheDir;

    protected function setUp(): void
    {
        $this->tempCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vapehaus_cache_test_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tempCacheDir)) {
            mkdir($this->tempCacheDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempCacheDir)) {
            $files = glob($this->tempCacheDir . DIRECTORY_SEPARATOR . '*');
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempCacheDir);
        }
    }

    public function testInMemoryCacheBasicOperations(): void
    {
        $cache = new InMemoryCache();

        $this->assertNull($cache->get('non_existent'));
        $this->assertFalse($cache->has('non_existent'));

        $cache->set('user_1', ['id' => 1, 'name' => 'John'], 60);
        $this->assertTrue($cache->has('user_1'));

        $val = $cache->get('user_1');
        $this->assertEquals('John', $val['name']);

        $cache->delete('user_1');
        $this->assertFalse($cache->has('user_1'));
        $this->assertNull($cache->get('user_1'));
    }

    public function testInMemoryCacheRememberMethod(): void
    {
        $cache = new InMemoryCache();
        $counter = 0;

        $calc = function () use (&$counter) {
            $counter++;
            return 'computed_result';
        };

        $val1 = $cache->remember('my_key', 60, $calc);
        $this->assertEquals('computed_result', $val1);
        $this->assertEquals(1, $counter);

        // Second call should return cached value without invoking callback
        $val2 = $cache->remember('my_key', 60, $calc);
        $this->assertEquals('computed_result', $val2);
        $this->assertEquals(1, $counter);
    }

    public function testInMemoryCacheTagInvalidation(): void
    {
        $cache = new InMemoryCache();

        $cache->set('facets:brands', ['Geek Bar', 'OXVA'], 300, ['catalog', 'facets']);
        $cache->set('facets:categories', ['Disposables', 'Pods'], 300, ['catalog', 'facets']);
        $cache->set('other:data', 'stays', 300, ['other']);

        $this->assertTrue($cache->has('facets:brands'));
        $this->assertTrue($cache->has('facets:categories'));
        $this->assertTrue($cache->has('other:data'));

        $cache->invalidateTags(['facets']);

        $this->assertFalse($cache->has('facets:brands'));
        $this->assertFalse($cache->has('facets:categories'));
        $this->assertTrue($cache->has('other:data'));
    }

    public function testFileCachePersistenceAndRetrieval(): void
    {
        $cache = new FileCache($this->tempCacheDir);

        $data = ['brand' => 'RELX', 'count' => 12];
        $cache->set('brand_relx', $data, 300);

        $this->assertTrue($cache->has('brand_relx'));
        $retrieved = $cache->get('brand_relx');
        $this->assertEquals('RELX', $retrieved['brand']);
        $this->assertEquals(12, $retrieved['count']);

        // Test delete
        $cache->delete('brand_relx');
        $this->assertFalse($cache->has('brand_relx'));
    }

    public function testFileCacheTagInvalidation(): void
    {
        $cache = new FileCache($this->tempCacheDir);

        $cache->set('catalog_facets', ['price_min' => 200], 300, ['catalog']);
        $cache->set('catalog_categories', ['Disposables'], 300, ['catalog']);
        $cache->set('settings', ['theme' => 'dark'], 300, ['settings']);

        $this->assertTrue($cache->has('catalog_facets'));
        $this->assertTrue($cache->has('catalog_categories'));
        $this->assertTrue($cache->has('settings'));

        $cache->invalidateTags(['catalog']);

        $this->assertFalse($cache->has('catalog_facets'));
        $this->assertFalse($cache->has('catalog_categories'));
        $this->assertTrue($cache->has('settings'));
    }
}
