<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait LowercasesInput
{
    protected function lowercaseInput(string ...$keys): void
    {
        $this->merge(array_map(
            static fn (mixed $value): mixed => is_string($value)
                ? Str::of($value)->trim()->lower()->value()
                : $value,
            Arr::only($this->all(), $keys),
        ));
    }
}
