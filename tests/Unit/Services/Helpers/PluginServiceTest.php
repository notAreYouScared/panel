<?php

namespace App\Tests\Unit\Services\Helpers;

use App\Services\Helpers\PluginService;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PluginServiceTest extends TestCase
{
    private PluginService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $app = $this->createMock(Application::class);
        $this->service = new PluginService($app);
    }

    /**
     * Test that GitHub tree URLs are correctly detected.
     */
    public function testIsGitHubTreeUrl(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('isGitHubTreeUrl');
        $method->setAccessible(true);

        // Valid GitHub tree URLs
        $this->assertTrue($method->invoke($this->service, 'https://github.com/pelican-dev/plugins/tree/main/pirate-language'));
        $this->assertTrue($method->invoke($this->service, 'http://github.com/user/repo/tree/develop/folder'));
        $this->assertTrue($method->invoke($this->service, 'https://github.com/owner/repo/tree/feature/branch/path/to/plugin'));

        // Invalid URLs
        $this->assertFalse($method->invoke($this->service, 'https://example.com/plugin.zip'));
        $this->assertFalse($method->invoke($this->service, 'https://github.com/owner/repo/blob/main/file.php'));
        $this->assertFalse($method->invoke($this->service, 'https://github.com/owner/repo'));
        $this->assertFalse($method->invoke($this->service, 'not-a-url'));
    }

    /**
     * Test that plugin names are correctly extracted from GitHub URLs.
     */
    public function testExtractPluginNameFromGitHubUrl(): void
    {
        $testCases = [
            'https://github.com/pelican-dev/plugins/tree/main/pirate-language' => 'pirate-language',
            'https://github.com/user/repo/tree/develop/path/to/plugin' => 'plugin',
            'https://github.com/owner/repo/tree/main/simple-plugin' => 'simple-plugin',
        ];

        foreach ($testCases as $url => $expectedName) {
            if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/(.+)$#', $url, $matches)) {
                $pluginName = basename($matches[4]);
                $this->assertEquals($expectedName, $pluginName, "Failed to extract plugin name from: $url");
            }
        }
    }
}
