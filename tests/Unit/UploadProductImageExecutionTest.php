<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Execution\Admin\UploadProductImageExecution;
use Vapehaus\Infrastructure\Storage\StorageInterface;

final class UploadProductImageExecutionTest extends TestCase
{
    public function testUploadSuccessWithBase64DataUri(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $mockStorage->expects($this->once())
            ->method('upload')
            ->with(
                $this->equalTo('test-binary-data'),
                $this->matchesRegularExpression('/^prod_[a-f0-9]+\.webp$/'),
                $this->equalTo('image/webp'),
                $this->equalTo('products')
            )
            ->willReturn([
                'url' => 'https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_123.webp',
                'filename' => 'prod_123.webp',
                'size' => 16,
            ]);

        $execution = new UploadProductImageExecution($mockStorage);

        $payload = [
            'image_data' => 'data:image/webp;base64,' . base64_encode('test-binary-data'),
            'filename' => 'sample.webp',
        ];

        $result = $execution->execute($payload);

        $this->assertSame('https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_123.webp', $result['url']);
        $this->assertSame('prod_123.webp', $result['filename']);
        $this->assertSame(16, $result['size']);
    }

    public function testUploadRejectsEmptyData(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $execution = new UploadProductImageExecution($mockStorage);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No image data provided for upload.');

        $execution->execute(['image_data' => '']);
    }

    public function testUploadRejectsInvalidBase64(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $execution = new UploadProductImageExecution($mockStorage);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid base64');

        $execution->execute(['image_data' => 'data:image/webp;base64,!!!NotBase64!!!']);
    }

    public function testUploadRejectsUnsupportedFormat(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $execution = new UploadProductImageExecution($mockStorage);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unsupported image format: gif');

        $execution->execute([
            'image_data' => 'data:image/gif;base64,' . base64_encode('fake-gif-data'),
        ]);
    }

    public function testNoLocalUploadFolderIsCreated(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $mockStorage->method('upload')->willReturn([
            'url' => 'https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_clean.webp',
            'filename' => 'prod_clean.webp',
            'size' => 10,
        ]);

        $execution = new UploadProductImageExecution($mockStorage);

        $result = $execution->execute([
            'image_data' => 'data:image/webp;base64,' . base64_encode('0123456789'),
        ]);

        $this->assertStringContainsString('supabase.co/storage/v1/object/public/products/', $result['url']);
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../../public/uploads/products');
    }
}
