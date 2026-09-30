<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SafeRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SafeRedirectTest extends TestCase
{
    public function test_accepts_an_internal_path_with_query(): void
    {
        $this->assertSame('/admin/tours/6/edit?tab=departures', SafeRedirect::internalPath('/admin/tours/6/edit?tab=departures'));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsafeValues(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'array' => [['/admin']],
            'relative' => ['admin/departures'],
            'absolute url' => ['https://evil.example.com'],
            'protocol relative' => ['//evil.example.com'],
            'backslash' => ['/\\evil.example.com'],
            'embedded scheme' => ['/redirect?to=https://evil.example.com'],
            'control char' => ["/admin\r\nLocation: https://evil.example.com"],
        ];
    }

    #[DataProvider('unsafeValues')]
    public function test_rejects_unsafe_values(mixed $value): void
    {
        $this->assertNull(SafeRedirect::internalPath($value));
    }
}
