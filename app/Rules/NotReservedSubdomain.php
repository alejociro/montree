<?php

declare(strict_types=1);

namespace App\Rules;

use App\Multitenancy\SubdomainTenantFinder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Config;

final class NotReservedSubdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $slug = mb_strtolower($value);
        $platformHost = (string) Config::get('montree.platform_host', 'montree.test');

        if (SubdomainTenantFinder::isReservedSlug($slug) || SubdomainTenantFinder::isReservedHost($slug.'.'.$platformHost)) {
            $fail(__('Ese subdominio no está disponible.'));
        }
    }
}
