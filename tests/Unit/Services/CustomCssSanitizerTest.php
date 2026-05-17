<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tenant\CustomCssSanitizer;
use PHPUnit\Framework\TestCase;

class CustomCssSanitizerTest extends TestCase
{
    private CustomCssSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new CustomCssSanitizer;
    }

    public function test_accepts_whitelisted_properties_inside_tenant_selectors(): void
    {
        $css = '.tenant-banner { color: #fff; background-color: #16a34a; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: #fff', $result['css']);
        $this->assertStringContainsString('background-color: #16a34a', $result['css']);
        $this->assertSame([], $result['removed']);
    }

    public function test_strips_properties_outside_whitelist(): void
    {
        $css = '.tenant-banner { color: #fff; position: absolute; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: #fff', $result['css']);
        $this->assertStringNotContainsString('position', $result['css']);
        $this->assertNotEmpty($result['removed']);
    }

    public function test_blocks_expression_javascript_payload(): void
    {
        $css = '.tenant-banner { color: expression(alert(1)); }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertSame('', $result['css']);
        $this->assertNotEmpty($result['removed']);
    }

    public function test_blocks_javascript_url_payload(): void
    {
        $css = '.tenant-banner { background: url(javascript:alert(1)); }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertSame('', $result['css']);
        $this->assertNotEmpty($result['removed']);
    }

    public function test_blocks_at_import(): void
    {
        $css = '@import url("https://evil.example.com/payload.css"); .tenant-banner { color: red; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertSame('', $result['css']);
        $this->assertNotEmpty($result['removed']);
    }

    public function test_rejects_selectors_targeting_global_scope(): void
    {
        $css = 'body { color: red; } * { padding: 0; } :root { --primary: red; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('body', $result['css']);
        $this->assertStringNotContainsString('*', $result['css']);
        $this->assertStringNotContainsString(':root', $result['css']);
        $this->assertNotEmpty($result['removed']);
    }

    public function test_accepts_safe_css_variables_inside_class_selector(): void
    {
        $css = '.tenant-theme { --primary: #16a34a; --secondary: #0f766e; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('--primary: #16a34a', $result['css']);
        $this->assertStringContainsString('--secondary: #0f766e', $result['css']);
    }

    public function test_rejects_unknown_css_variables(): void
    {
        $css = '.tenant-theme { --secret-color: red; --primary: blue; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('--primary: blue', $result['css']);
        $this->assertStringNotContainsString('--secret-color', $result['css']);
    }
}
