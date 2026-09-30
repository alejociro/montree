<?php

declare(strict_types=1);

namespace App\Data\SuperAdmin;

use App\Http\Requests\SuperAdmin\TenantIndexRequest;

final readonly class TenantFilters
{
    public function __construct(
        public ?string $search,
        public ?string $status,
        public string $sort,
        public string $direction,
    ) {}

    public static function fromRequest(TenantIndexRequest $request): self
    {
        return new self(
            search: $request->search(),
            status: $request->status(),
            sort: $request->sort(),
            direction: $request->direction(),
        );
    }

    /**
     * @return array{search: string|null, status: string|null, sort: string, direction: string}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }
}
