<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Execution\Admin\AdminDeleteProductExecution;
use Vapehaus\Tests\Mocks\InMemoryProductRepository;
use Vapehaus\Tests\TestCase;

final class AdminDeleteProductExecutionTest extends TestCase
{
    public function testDeleteExistingProductSucceeds(): void
    {
        $repo = new InMemoryProductRepository();
        $product = new Product('prd_test_del', 'cat_disposables', 'Delete Me Vape', 'delete-me-vape', 'Test Brand');
        $repo->save($product);

        $this->assertNotNull($repo->findById('prd_test_del'));

        $execution = new AdminDeleteProductExecution($repo);
        $result = $execution->execute('prd_test_del');

        $this->assertEquals('prd_test_del', $result['id']);
        $this->assertEquals('Delete Me Vape', $result['name']);
        $this->assertTrue($result['deleted']);
        $this->assertNull($repo->findById('prd_test_del'));
    }

    public function testDeleteNonExistentProductThrows(): void
    {
        $repo = new InMemoryProductRepository();
        $execution = new AdminDeleteProductExecution($repo);

        $this->expectException(EntityNotFoundException::class);
        $execution->execute('prd_non_existent');
    }
}
