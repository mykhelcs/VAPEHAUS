<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use Vapehaus\Tests\TestCase;

class DesignSystemConstraintTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = dirname(__DIR__, 2) . '/public';
    }

    public function testLogoSvgFilesExistAndHaveNoGradients(): void
    {
        $logoPath = $this->publicDir . '/images/logo.svg';
        $iconPath = $this->publicDir . '/images/logo-icon.svg';

        $this->assertTrue(file_exists($logoPath), "logo.svg must exist at {$logoPath}");
        $this->assertTrue(file_exists($iconPath), "logo-icon.svg must exist at {$iconPath}");

        $logoContent = (string)file_get_contents($logoPath);
        $iconContent = (string)file_get_contents($iconPath);

        // Assert valid SVG xml
        $logoXml = simplexml_load_string($logoContent);
        $iconXml = simplexml_load_string($iconContent);

        $this->assertNotNull($logoXml, 'logo.svg must be valid XML/SVG');
        $this->assertNotNull($iconXml, 'logo-icon.svg must be valid XML/SVG');

        // Strictly NO gradients in the SVG logos
        $this->assertFalse(str_contains($logoContent, 'linearGradient'), 'logo.svg must not contain linearGradient');
        $this->assertFalse(str_contains($logoContent, 'radialGradient'), 'logo.svg must not contain radialGradient');
        $this->assertFalse(str_contains($iconContent, 'linearGradient'), 'logo-icon.svg must not contain linearGradient');
        $this->assertFalse(str_contains($iconContent, 'radialGradient'), 'logo-icon.svg must not contain radialGradient');
    }

    public function testCssFilesHaveNoGradients(): void
    {
        $cssFiles = [
            $this->publicDir . '/css/theme.css',
            $this->publicDir . '/css/storefront.css',
            $this->publicDir . '/css/admin.css',
        ];

        foreach ($cssFiles as $file) {
            $this->assertTrue(file_exists($file), "CSS file must exist: {$file}");
            $content = (string)file_get_contents($file);

            $this->assertFalse(
                str_contains($content, 'linear-gradient'),
                "CSS file " . basename($file) . " must not contain linear-gradient"
            );
            $this->assertFalse(
                str_contains($content, 'radial-gradient'),
                "CSS file " . basename($file) . " must not contain radial-gradient"
            );
        }
    }

    public function testIconsModuleExistsAndContainsCanvaStyleSvgs(): void
    {
        $iconsPath = $this->publicDir . '/js/icons.js';
        $this->assertTrue(file_exists($iconsPath), "icons.js must exist at {$iconsPath}");

        $content = (string)file_get_contents($iconsPath);
        $this->assertTrue(str_contains($content, 'const Icons ='), 'icons.js must define Icons object');
        $this->assertTrue(str_contains($content, 'cart'), 'Icons must define cart icon');
        $this->assertTrue(str_contains($content, 'search'), 'Icons must define search icon');
        $this->assertTrue(str_contains($content, 'user'), 'Icons must define user icon');
    }
}
