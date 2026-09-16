<?php

declare(strict_types=1);

namespace App\Data;

final readonly class HandoffPayload
{
    public function __construct(
        public int $userId,
        public string $redirectTo,
        public bool $remember = false,
    ) {}

    /**
     * @param  array{user_id: int, redirect_to: string, remember?: bool}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            userId: $payload['user_id'],
            redirectTo: $payload['redirect_to'],
            remember: $payload['remember'] ?? false,
        );
    }

    /**
     * @return array{user_id: int, redirect_to: string, remember: bool}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'redirect_to' => $this->redirectTo,
            'remember' => $this->remember,
        ];
    }
}
