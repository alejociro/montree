<?php

declare(strict_types=1);

namespace App\Data\Tour;

use App\Http\Requests\Admin\Tour\TourIndexRequest;

final readonly class TourFilters
{
    public function __construct(
        public ?string $status,
        public ?int $categoryId,
        public ?string $search,
        public string $sort,
        public string $direction,
    ) {}

    public static function fromRequest(TourIndexRequest $request): self
    {
        return new self(
            status: $request->status(),
            categoryId: $request->categoryId(),
            search: $request->search(),
            sort: $request->sort(),
            direction: $request->direction(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'category_id' => $this->categoryId,
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }
}
